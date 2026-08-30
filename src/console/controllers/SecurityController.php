<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;

/**
 * Security utilities for ShortLink Manager
 *
 * @since 5.0.0
 */
class SecurityController extends Controller
{
    private const IP_SALT_ENV_VAR = 'SHORTLINK_MANAGER_IP_SALT';

    /**
     * Generate a secure salt for IP hashing and optionally update .env file
     *
     * @return int
     */
    public function actionGenerateSalt(): int
    {
        $pluginName = \lindemannrock\shortlinkmanager\ShortLinkManager::$plugin->getSettings()->getFullName();
        $this->stdout("{$pluginName} - IP Hash Salt Generator\n", Console::FG_CYAN);
        $this->stdout(str_repeat('=', 60) . "\n\n");

        // Generate cryptographically secure random salt
        $salt = bin2hex(random_bytes(32)); // 64-character hex string

        $this->stdout("Generated secure salt:\n", Console::FG_YELLOW);
        $this->stdout($salt . "\n\n", Console::FG_GREEN);

        // Check if .env file exists and try to update it
        $envPath = $this->envPath();

        if (!$this->fileExists($envPath)) {
            $this->stdout("Warning: .env file not found at: {$envPath}\n\n", Console::FG_RED);
            $this->stdout("Manually add this to your .env file:\n", Console::FG_CYAN);
            $this->stdout(self::IP_SALT_ENV_VAR . "=\"{$salt}\"\n\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        // Read current .env file
        $envContent = $this->readFile($envPath);
        if ($envContent === false) {
            return $this->failWithManualAssignment($salt);
        }

        $saltExists = $this->matchEnvironmentAssignment($envContent);
        if ($saltExists === false) {
            return $this->failWithManualAssignment($salt);
        }

        if ($saltExists) {
            $this->stdout('Existing ' . self::IP_SALT_ENV_VAR . " found in .env\n\n", Console::FG_YELLOW);
            $this->stdout("WARNING: ", Console::FG_RED);
            $this->stdout("Replacing the salt will break unique visitor tracking!\n");
            $this->stdout("All existing analytics will use the old hash values.\n\n");

            if (!$this->confirm('Do you want to replace the existing salt?', false)) {
                $this->stdout("\nOperation cancelled. Existing salt unchanged.\n", Console::FG_YELLOW);
                return ExitCode::OK;
            }

            // Replace existing salt
            $envContent = $this->replaceEnvironmentAssignment($envContent, $salt);
            if ($envContent === null) {
                return $this->failWithManualAssignment($salt);
            }
            $action = "Updated";
        } else {
            // Append new salt
            if (!empty($envContent) && substr($envContent, -1) !== "\n") {
                $envContent .= "\n";
            }
            $envContent .= "\n# {$pluginName} IP Hash Salt (generated " . date('Y-m-d H:i:s') . ")\n";
            $envContent .= self::IP_SALT_ENV_VAR . '="' . $salt . '"' . "\n";
            $action = "Added";
        }

        // Write back to .env file
        if (!$this->replaceFileAtomically($envPath, $envContent)) {
            return $this->failWithManualAssignment($salt);
        }

        $this->stdout("\n✓ {$action} " . self::IP_SALT_ENV_VAR . " in .env file\n", Console::FG_GREEN);
        $this->stdout("Location: {$envPath}\n\n", Console::FG_CYAN);

        $this->stdout("Important:\n", Console::FG_YELLOW);
        $this->stdout("• Never commit .env to version control\n");
        $this->stdout("• Store the salt securely (password manager recommended)\n");
        $this->stdout("• Use the SAME salt across all environments (dev/staging/production)\n");
        $this->stdout("• Changing the salt will reset unique visitor tracking\n\n");

        return ExitCode::OK;
    }

    /**
     * Replace a file through a verified temporary file in the same directory.
     */
    protected function replaceFileAtomically(string $path, string $content): bool
    {
        $directory = dirname($path);
        $temporaryPath = $this->createTemporaryFile($directory, basename($path) . '.tmp-');
        if ($temporaryPath === false) {
            return false;
        }

        $temporaryHandle = null;

        try {
            if (dirname($temporaryPath) !== $directory) {
                return false;
            }

            $temporaryHandle = $this->openTemporaryFile($temporaryPath);
            if ($temporaryHandle === false) {
                $temporaryHandle = null;
                return false;
            }

            $written = $this->writeTemporaryFile($temporaryHandle, $content);
            if ($written !== strlen($content)) {
                return false;
            }

            if (!$this->flushTemporaryFile($temporaryHandle)) {
                return false;
            }

            $handleToClose = $temporaryHandle;
            $temporaryHandle = null;
            if (!$this->closeTemporaryFile($handleToClose)) {
                return false;
            }

            if ($this->readFile($temporaryPath) !== $content) {
                return false;
            }

            $existingMode = $this->getFileMode($path);
            if ($existingMode === false) {
                return false;
            }
            $expectedMode = $existingMode & 0777;
            if (!$this->setFileMode($temporaryPath, $expectedMode)) {
                return false;
            }

            $temporaryMode = $this->getFileMode($temporaryPath);
            if ($temporaryMode === false || ($temporaryMode & 0777) !== $expectedMode) {
                return false;
            }

            if (!$this->renameFile($temporaryPath, $path)) {
                return false;
            }

            $temporaryPath = null;
            return true;
        } finally {
            try {
                if ($temporaryHandle !== null) {
                    $handleToClose = $temporaryHandle;
                    $temporaryHandle = null;
                    $this->closeTemporaryFile($handleToClose);
                }
            } finally {
                if ($temporaryPath !== null && $this->fileExists($temporaryPath)) {
                    $this->deleteFile($temporaryPath);
                }
            }
        }
    }

    protected function failWithManualAssignment(string $salt): int
    {
        $this->stdout("\nError: Could not write to .env file\n", Console::FG_RED);
        $this->stdout("Please add manually:\n", Console::FG_CYAN);
        $this->stdout(self::IP_SALT_ENV_VAR . "=\"{$salt}\"\n\n", Console::FG_GREEN);
        return ExitCode::UNSPECIFIED_ERROR;
    }

    protected function matchEnvironmentAssignment(string $content): int|false
    {
        return preg_match('/^' . self::IP_SALT_ENV_VAR . '=/m', $content);
    }

    protected function replaceEnvironmentAssignment(string $content, string $salt): ?string
    {
        return preg_replace(
            '/^' . self::IP_SALT_ENV_VAR . '=.*$/m',
            self::IP_SALT_ENV_VAR . '="' . $salt . '"',
            $content
        );
    }

    protected function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    protected function readFile(string $path): string|false
    {
        return @file_get_contents($path);
    }

    protected function createTemporaryFile(string $directory, string $prefix): string|false
    {
        return @tempnam($directory, $prefix);
    }

    /**
     * @return resource|false
     */
    protected function openTemporaryFile(string $path): mixed
    {
        return @fopen($path, 'wb');
    }

    /**
     * @param resource $handle
     */
    protected function writeTemporaryFile(mixed $handle, string $content): int|false
    {
        return @fwrite($handle, $content);
    }

    /**
     * @param resource $handle
     */
    protected function flushTemporaryFile(mixed $handle): bool
    {
        return @fflush($handle);
    }

    /**
     * @param resource $handle
     */
    protected function closeTemporaryFile(mixed $handle): bool
    {
        return @fclose($handle);
    }

    protected function getFileMode(string $path): int|false
    {
        clearstatcache(true, $path);
        return @fileperms($path);
    }

    protected function setFileMode(string $path, int $mode): bool
    {
        return @chmod($path, $mode);
    }

    protected function renameFile(string $from, string $to): bool
    {
        return @rename($from, $to);
    }

    protected function deleteFile(string $path): bool
    {
        return @unlink($path);
    }

    protected function envPath(): string
    {
        return defined('CRAFT_BASE_PATH')
            ? CRAFT_BASE_PATH . DIRECTORY_SEPARATOR . '.env'
            : Craft::getAlias('@root/.env');
    }
}
