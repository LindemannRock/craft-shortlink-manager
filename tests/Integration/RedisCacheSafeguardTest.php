<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\base\helpers\CacheHelper;
use lindemannrock\shortlinkmanager\services\LocalCacheService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.20.0
 */
#[CoversClass(ShortLinkManager::class)]
#[CoversClass(LocalCacheService::class)]
#[CoversClass(CacheHelper::class)]
class RedisCacheSafeguardTest extends TestCase
{
    public function testRuntimeSourceUsesRedisSafeguardHelper(): void
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

        foreach ([
            $pluginRoot . '/src/services/LocalCacheService.php',
            $pluginRoot . '/src/services/QrCodeService.php',
        ] as $sourceFile) {
            $source = file_get_contents($sourceFile);
            $this->assertIsString($source);
            if (str_ends_with($sourceFile, 'LocalCacheService.php')) {
                $this->assertStringContainsString('CacheHelper::clearTrackedRedisKeys', $source);
            } else {
                $this->assertStringContainsString('PluginHelper::getRedisCacheOrLog', $source);
            }
        }
    }
}
