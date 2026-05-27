<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.20.0
 */
#[CoversClass(ShortLinkManager::class)]
#[CoversClass(PluginHelper::class)]
class RedisCacheSafeguardTest extends TestCase
{
    public function testRuntimeSourceUsesRedisSafeguardHelper(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $sourceFiles = [
            $pluginRoot . '/src/ShortLinkManager.php',
            $pluginRoot . '/src/controllers/SettingsController.php',
            $pluginRoot . '/src/services/QrCodeService.php',
            $pluginRoot . '/src/services/ShortLinksService.php',
        ];

        foreach ($sourceFiles as $sourceFile) {
            $source = file_get_contents($sourceFile);
            $this->assertIsString($source);
            $this->assertStringNotContainsString('instanceof \yii\redis\Cache', $source);
            $this->assertStringContainsString('PluginHelper::getRedisCacheOrLog', $source);
        }
    }
}
