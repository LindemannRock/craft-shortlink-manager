<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\helpers\FileHelper;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\console\ExitCode;

/**
 * Setup utilities for ShortLink Manager.
 *
 * @since 5.27.0
 */
final class SetupController extends Controller
{
    /**
     * @var string|null Template key to copy: redirect, expired, or qr.
     */
    public ?string $template = null;

    /**
     * @var bool Whether existing destination templates should be replaced.
     */
    public bool $overwrite = false;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'copy-templates') {
            $options[] = 'template';
            $options[] = 'overwrite';
        }

        return $options;
    }

    /**
     * Copy bundled starter templates into the configured site template paths.
     */
    public function actionCopyTemplates(): int
    {
        $plugin = ShortLinkManager::$plugin;
        $statuses = $plugin->setup->templateStatuses($plugin->getSettings());
        $selectedStatuses = $this->selectedTemplateStatuses($statuses);

        if ($selectedStatuses === []) {
            $allowed = implode(', ', array_column($statuses, 'key'));
            $this->stderr("Unknown template \"{$this->template}\". Use one of: {$allowed}.\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        $copied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($selectedStatuses as $status) {
            $result = $this->copyTemplate($status);
            if ($result === 'copied') {
                $copied++;
            } elseif ($result === 'skipped') {
                $skipped++;
            } else {
                $failed++;
            }
        }

        $this->stdout("\nSummary: {$copied} copied, {$skipped} skipped, {$failed} failed.\n");

        return $failed === 0 ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * @param array<int, array{key: string, label: string, source: string, destination: string, destinationExists: bool, exists: bool}> $statuses
     * @return array<int, array{key: string, label: string, source: string, destination: string, destinationExists: bool, exists: bool}>
     */
    private function selectedTemplateStatuses(array $statuses): array
    {
        $template = $this->template !== null ? strtolower(trim($this->template)) : null;

        if ($template === null || $template === '') {
            return $statuses;
        }

        return array_values(array_filter(
            $statuses,
            static fn(array $status): bool => $status['key'] === $template
        ));
    }

    /**
     * @param array{key: string, label: string, source: string, destination: string, destinationExists: bool, exists: bool} $status
     */
    private function copyTemplate(array $status): string
    {
        $sourcePath = $this->absoluteProjectPath($status['source']);
        $destinationPath = $this->absoluteTemplatePath($status['destination']);

        if ($status['exists'] && !$status['destinationExists']) {
            $this->stdout("Skipped {$status['label']}: template already resolves for all enabled sites.\n", Console::FG_YELLOW);
            return 'skipped';
        }

        if ($status['destinationExists'] && !$this->shouldOverwrite($status)) {
            $this->stdout("Skipped {$status['label']}: destination already exists ({$status['destination']}).\n", Console::FG_YELLOW);
            return 'skipped';
        }

        if (!is_file($sourcePath)) {
            $this->stderr("Failed {$status['label']}: source not found ({$status['source']}).\n", Console::FG_RED);
            return 'failed';
        }

        $destinationDir = dirname($destinationPath);
        if (!is_dir($destinationDir) && !FileHelper::createDirectory($destinationDir)) {
            $this->stderr("Failed {$status['label']}: could not create destination directory.\n", Console::FG_RED);
            return 'failed';
        }

        if (!copy($sourcePath, $destinationPath)) {
            $this->stderr("Failed {$status['label']}: could not copy to {$status['destination']}.\n", Console::FG_RED);
            return 'failed';
        }

        $this->stdout("Copied {$status['label']} to {$status['destination']}.\n", Console::FG_GREEN);
        return 'copied';
    }

    /**
     * @param array{label: string, destination: string} $status
     */
    private function shouldOverwrite(array $status): bool
    {
        if ($this->overwrite) {
            return true;
        }

        if ($this->template !== null && $this->interactive) {
            return $this->confirm("{$status['label']} already exists at {$status['destination']}. Overwrite it?", false);
        }

        return false;
    }

    private function absoluteProjectPath(string $path): string
    {
        $root = defined('CRAFT_BASE_PATH') ? CRAFT_BASE_PATH : Craft::getAlias('@root');

        return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private function absoluteTemplatePath(string $destination): string
    {
        $relativeDestination = trim(preg_replace('#^templates/?#', '', $destination) ?? '', '/');

        return Craft::$app->getPath()->getSiteTemplatesPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDestination);
    }
}
