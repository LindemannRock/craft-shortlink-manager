<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use craft\helpers\FileHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\shortlinkmanager\services\LocalCacheService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Pins local cache-clearing behavior and implementation boundaries.
 *
 * @since 5.25.0
 */
#[CoversClass(LocalCacheService::class)]
final class LocalCacheServiceTest extends TestCase
{
    public function testFileCacheClearingDeletesOnlyCacheFiles(): void
    {
        $cachePath = PluginHelper::getCachePath(ShortLinkManager::$plugin, 'qr');
        FileHelper::createDirectory($cachePath);

        $cacheFile = $cachePath . 'local-cache-service-test.cache';
        $nestedCacheFile = $cachePath . 'local-cache-service-test-nested.cache';
        $nonCacheFile = $cachePath . 'local-cache-service-test.txt';

        file_put_contents($cacheFile, 'cache');
        file_put_contents($nestedCacheFile, 'cache');
        file_put_contents($nonCacheFile, 'keep');

        try {
            $this->withSettings([
                'cacheStorageMethod' => 'file',
            ], function() use ($cacheFile, $nestedCacheFile, $nonCacheFile): void {
                $cleared = ShortLinkManager::$plugin->localCache->clearQrCache();

                self::assertGreaterThanOrEqual(2, $cleared);
                self::assertFileDoesNotExist($cacheFile);
                self::assertFileDoesNotExist($nestedCacheFile);
                self::assertFileExists($nonCacheFile);
            });
        } finally {
            @unlink($cacheFile);
            @unlink($nestedCacheFile);
            @unlink($nonCacheFile);
        }
    }

    public function testCacheClearSourcesUseBatchedAndStreamingImplementation(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $localCacheSource = file_get_contents($pluginRoot . '/src/services/LocalCacheService.php');
        self::assertIsString($localCacheSource);
        self::assertStringContainsString('CacheHelper::clearTrackedRedisKeys', $localCacheSource);
        self::assertStringContainsString('CacheHelper::clearCacheFiles', $localCacheSource);
        self::assertStringContainsString("QR_CACHE_KEY_TYPE = 'qr'", $localCacheSource);
        self::assertStringContainsString("QR_CACHE_DIRECTORY = 'qr'", $localCacheSource);
        self::assertStringContainsString("DEVICE_CACHE_KEY_TYPE = 'device'", $localCacheSource);
        self::assertStringContainsString("DEVICE_CACHE_DIRECTORY = 'device'", $localCacheSource);
        self::assertStringNotContainsString('SSCAN', $localCacheSource);
        self::assertStringNotContainsString('DirectoryIterator', $localCacheSource);
        self::assertStringNotContainsString("executeCommand('SMEMBERS'", $localCacheSource);
        self::assertStringNotContainsString('glob(', $localCacheSource);

        foreach ([
            $pluginRoot . '/src/ShortLinkManager.php',
            $pluginRoot . '/src/controllers/SettingsController.php',
            $pluginRoot . '/src/services/ShortLinksService.php',
            $pluginRoot . '/src/utilities/ShortLinkManagerUtility.php',
        ] as $sourceFile) {
            $source = file_get_contents($sourceFile);
            self::assertIsString($source);
            self::assertStringNotContainsString("executeCommand('SMEMBERS'", $source);
            self::assertStringNotContainsString('glob(', $source);
        }
    }
}
