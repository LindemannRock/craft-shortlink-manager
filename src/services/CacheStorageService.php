<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\services;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use lindemannrock\base\cache\DisposableCacheStorageDecision;
use lindemannrock\base\cache\DisposableCacheStorageResolver;
use lindemannrock\base\cache\ScopedCache;
use lindemannrock\base\cache\ScopedCacheResult;
use lindemannrock\base\helpers\CacheHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Resolves and operates ShortLink Manager's disposable cache storage.
 *
 * @since 5.29.0
 */
final class CacheStorageService extends Component
{
    public const FAMILY_QR = 'qr';
    public const FAMILY_DEVICE = 'device';

    /** @var array<string, true> */
    private const OWNED_FAMILIES = [
        self::FAMILY_QR => true,
        self::FAMILY_DEVICE => true,
    ];

    /** @var array<string, true> */
    private static array $loggedFailures = [];

    public function getStorageDecision(?string $configuredStorage = null): DisposableCacheStorageDecision
    {
        $configuredStorage ??= ShortLinkManager::$plugin->getSettings()->cacheStorageMethod;

        return (new DisposableCacheStorageResolver())->resolve(
            configuredStorageToken: $configuredStorage,
            diagnosticContext: ShortLinkManager::$plugin->id . ':disposable-cache',
        );
    }

    public function readQrCode(string $itemIdentity, int $ttl): ScopedCacheResult
    {
        if ($ttl <= 0) {
            return ScopedCacheResult::failure();
        }

        $decision = $this->getStorageDecision();

        if ($decision->usesApplicationCache()) {
            $cache = $this->getScopedCache($decision, self::FAMILY_QR);
            if ($cache === null) {
                return ScopedCacheResult::failure();
            }

            $result = $cache->get($itemIdentity);
            if ($result->isFailure()) {
                $this->logFailure(self::FAMILY_QR, 'read');
            }

            return $result;
        }

        if ($decision->usesFileCache()) {
            return $this->readQrFile($itemIdentity, $ttl);
        }

        return ScopedCacheResult::miss();
    }

    public function writeQrCode(string $itemIdentity, string $value, int $ttl): bool
    {
        if ($ttl <= 0) {
            return false;
        }

        $decision = $this->getStorageDecision();

        if ($decision->usesApplicationCache()) {
            $written = $this->getScopedCache($decision, self::FAMILY_QR)?->set($itemIdentity, $value, $ttl) === true;
            if (!$written) {
                $this->logFailure(self::FAMILY_QR, 'write');
            }

            return $written;
        }

        if ($decision->usesFileCache()) {
            return $this->writeQrFile($itemIdentity, $value);
        }

        return false;
    }

    public function clearFamily(
        string $family,
        ?DisposableCacheStorageDecision $decision = null,
    ): int {
        $this->assertOwnedFamily($family);
        $decision ??= $this->getStorageDecision();

        if ($decision->usesApplicationCache()) {
            $invalidated = $this->getScopedCache($decision, $family)?->invalidateFamily() === true;
            if (!$invalidated) {
                $this->logFailure($family, 'invalidate');
            }

            return 0;
        }

        if (!$decision->usesFileCache()) {
            return 0;
        }

        try {
            return CacheHelper::clearCacheFiles($this->getFilePath($family));
        } catch (\Throwable $e) {
            $this->logFailure($family, 'clear-files', $e);
            return 0;
        }
    }

    public function countFiles(
        string $family,
        ?DisposableCacheStorageDecision $decision = null,
    ): int {
        $this->assertOwnedFamily($family);
        $decision ??= $this->getStorageDecision();
        if (!$decision->usesFileCache()) {
            return 0;
        }

        try {
            return CacheHelper::countCacheFiles($this->getFilePath($family));
        } catch (\Throwable $e) {
            $this->logFailure($family, 'count-files', $e);
            return 0;
        }
    }

    public function getDisplayFilePath(?DisposableCacheStorageDecision $decision = null): ?string
    {
        $decision ??= $this->getStorageDecision();
        if (!$decision->usesFileCache() || !$decision->filePathEligible) {
            return null;
        }

        return PluginHelper::getCacheBasePath(ShortLinkManager::$plugin);
    }

    private function readQrFile(string $itemIdentity, int $ttl): ScopedCacheResult
    {
        try {
            $cacheFile = $this->getQrCacheFile($itemIdentity);
            if (!@is_file($cacheFile)) {
                return ScopedCacheResult::miss();
            }

            $mtime = @filemtime($cacheFile);
            if ($mtime === false) {
                $this->logFailure(self::FAMILY_QR, 'file-timestamp');
                return ScopedCacheResult::failure();
            }
            if (time() - $mtime > $ttl) {
                @unlink($cacheFile);
                return ScopedCacheResult::miss();
            }

            $value = @file_get_contents($cacheFile);
            if (!is_string($value)) {
                $this->logFailure(self::FAMILY_QR, 'file-read');
                return ScopedCacheResult::failure();
            }

            return ScopedCacheResult::hit($value);
        } catch (\Throwable $e) {
            $this->logFailure(self::FAMILY_QR, 'file-read', $e);
            return ScopedCacheResult::failure();
        }
    }

    private function writeQrFile(string $itemIdentity, string $value): bool
    {
        try {
            $cachePath = $this->getFilePath(self::FAMILY_QR);
            if (!@is_dir($cachePath)) {
                FileHelper::createDirectory($cachePath);
            }

            if (@file_put_contents($this->getQrCacheFile($itemIdentity), $value, LOCK_EX) === false) {
                throw new \RuntimeException('QR cache file could not be written.');
            }

            return true;
        } catch (\Throwable $e) {
            $this->logFailure(self::FAMILY_QR, 'file-write', $e);
            return false;
        }
    }

    private function getScopedCache(
        DisposableCacheStorageDecision $decision,
        string $family,
    ): ?ScopedCache {
        $this->assertOwnedFamily($family);
        if ($decision->applicationCache === null) {
            return null;
        }

        try {
            return new ScopedCache(
                $decision->applicationCache,
                ShortLinkManager::$plugin->id,
                $family,
            );
        } catch (\Throwable $e) {
            $this->logFailure($family, 'initialize', $e);
            return null;
        }
    }

    private function getQrCacheFile(string $itemIdentity): string
    {
        return $this->getFilePath(self::FAMILY_QR) . md5($itemIdentity) . '.cache';
    }

    private function getFilePath(string $family): string
    {
        $this->assertOwnedFamily($family);

        return PluginHelper::getCachePath(ShortLinkManager::$plugin, $family);
    }

    private function assertOwnedFamily(string $family): void
    {
        if (!isset(self::OWNED_FAMILIES[$family])) {
            throw new \InvalidArgumentException('Unsupported ShortLink Manager cache family.');
        }
    }

    private function logFailure(string $family, string $operation, ?\Throwable $exception = null): void
    {
        $key = $family . ':' . $operation;
        if (isset(self::$loggedFailures[$key])) {
            return;
        }
        self::$loggedFailures[$key] = true;

        Craft::warning(sprintf(
            'ShortLink Manager %s cache %s failed%s; the value will be recomputed.',
            $family,
            $operation,
            $exception === null ? '' : ' (' . $exception::class . ')',
        ), ShortLinkManager::$plugin->id);
    }
}
