<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\shortlinkmanager\services\CacheStorageService;
use lindemannrock\shortlinkmanager\services\LocalCacheService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.20.0
 */
#[CoversClass(ShortLinkManager::class)]
#[CoversClass(LocalCacheService::class)]
#[CoversClass(CacheStorageService::class)]
class RedisCacheSafeguardTest extends TestCase
{
    public function testRuntimeSourceUsesBackendNeutralScopedCacheWithoutRawRedisOperations(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $sourceFiles = [
            $pluginRoot . '/src/ShortLinkManager.php',
            $pluginRoot . '/src/services/LocalCacheService.php',
            $pluginRoot . '/src/services/QrCodeService.php',
            $pluginRoot . '/src/services/ShortLinksService.php',
        ];

        foreach ($sourceFiles as $sourceFile) {
            $source = file_get_contents($sourceFile);
            $this->assertIsString($source);
            $this->assertStringNotContainsString('instanceof \yii\redis\Cache', $source);
        }

        foreach ($sourceFiles as $sourceFile) {
            $source = file_get_contents($sourceFile);
            $this->assertIsString($source);
            foreach (['getRedisCacheOrLog', 'clearTrackedRedisKeys', 'SADD', 'SMEMBERS', 'SREM', 'SSCAN'] as $legacyOperation) {
                $this->assertStringNotContainsString($legacyOperation, $source);
            }
        }

        $cacheStorage = file_get_contents($pluginRoot . '/src/services/CacheStorageService.php');
        $this->assertIsString($cacheStorage);
        $this->assertStringContainsString('new DisposableCacheStorageResolver()', $cacheStorage);
        $this->assertStringContainsString('new ScopedCache(', $cacheStorage);
        $this->assertStringContainsString('$decision->applicationCache', $cacheStorage);
    }
}
