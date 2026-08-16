<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use Craft;
use craft\cachecascade\CascadeCache;
use craft\helpers\FileHelper;
use lindemannrock\base\cache\ScopedCache;
use lindemannrock\base\device\DeviceDetection;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\shortlinkmanager\services\CacheStorageService;
use lindemannrock\shortlinkmanager\services\DeviceDetectionService;
use lindemannrock\shortlinkmanager\services\LocalCacheService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use yii\caching\CacheInterface;

require_once dirname(__DIR__) . '/Fixtures/CascadeCache.php';

/**
 * Pins local cache-clearing behavior and implementation boundaries.
 *
 * @since 5.25.0
 */
#[CoversClass(LocalCacheService::class)]
final class LocalCacheServiceTest extends TestCase
{
    private string $originalRuntimePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalRuntimePath = Craft::$app->getRuntimePath();
        Craft::$app->setRuntimePath($this->createTrackedTempDirectory('shortlink-local-cache-'));
    }

    protected function tearDown(): void
    {
        Craft::$app->setRuntimePath($this->originalRuntimePath);
        parent::tearDown();
    }

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

    public function testApplicationClearUsesGenerationFamiliesWithoutRedisEnumeration(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $localCacheSource = file_get_contents($pluginRoot . '/src/services/LocalCacheService.php');
        self::assertIsString($localCacheSource);
        self::assertStringContainsString('cacheStorage->clearFamily', $localCacheSource);
        self::assertStringContainsString('deviceDetection->clearCache', $localCacheSource);

        foreach ([
            $pluginRoot . '/src/ShortLinkManager.php',
            $pluginRoot . '/src/controllers/SettingsController.php',
            $pluginRoot . '/src/services/CacheStorageService.php',
            $pluginRoot . '/src/services/DeviceDetectionService.php',
            $pluginRoot . '/src/services/LocalCacheService.php',
            $pluginRoot . '/src/services/QrCodeService.php',
            $pluginRoot . '/src/services/ShortLinksService.php',
            $pluginRoot . '/src/utilities/ShortLinkManagerUtility.php',
        ] as $sourceFile) {
            $source = file_get_contents($sourceFile);
            self::assertIsString($source);
            foreach (['SADD', 'SMEMBERS', 'SREM', 'SSCAN', 'KEYS', 'SCAN', 'flush(', 'glob('] as $forbiddenOperation) {
                self::assertStringNotContainsString($forbiddenOperation, $source);
            }
        }
    }

    public function testDeviceClearInvalidatesOnlyDeviceFamilyAndResetsRequestLocalDetector(): void
    {
        $originalCache = Craft::$app->getCache();
        $originalRequest = Craft::$app->getRequest();
        self::assertInstanceOf(CacheInterface::class, $originalCache);
        $cache = new CascadeCache();
        Craft::$app->set('cache', $cache);
        Craft::$app->set('request', new class() extends \craft\console\Request {
            public function getQueryParam($name, $defaultValue = null): mixed
            {
                return $defaultValue;
            }

            public function getHeaders(): \yii\web\HeaderCollection
            {
                return new \yii\web\HeaderCollection();
            }
        });

        try {
            $this->withSettings([
                'cacheStorageMethod' => 'craft',
                'cacheDeviceDetection' => true,
                'deviceDetectionCacheDuration' => 79,
            ], function() use ($cache): void {
                $service = new DeviceDetectionService();
                ShortLinkManager::$plugin->set('deviceDetection', $service);
                $userAgent = 'ShortlinkCache/1.0';
                $detected = $service->detectDevice($userAgent);
                self::assertIsArray($detected);

                $detector = new \ReflectionProperty(DeviceDetectionService::class, 'deviceDetection');
                self::assertInstanceOf(DeviceDetection::class, $detector->getValue($service));

                $device = new ScopedCache($cache, ShortLinkManager::$plugin->id, CacheStorageService::FAMILY_DEVICE);
                $qr = new ScopedCache($cache, ShortLinkManager::$plugin->id, CacheStorageService::FAMILY_QR);
                $sentinel = new ScopedCache($cache, 'unrelated-plugin', 'sentinel');
                $deviceIdentity = [
                    'legacyPrefix' => PluginHelper::getCacheKeyPrefix(ShortLinkManager::$plugin->id, 'device'),
                    'device' => $userAgent,
                ];
                self::assertTrue($device->set($deviceIdentity, ['cacheMarker' => 'reused'], 60));
                self::assertTrue($qr->set('owned', 'qr', 60));
                self::assertTrue($sentinel->set('owned', 'safe', 60));
                self::assertSame(['cacheMarker' => 'reused'], (new DeviceDetectionService())->detectDevice($userAgent));
                self::assertContains(79, $cache->setDurations);

                self::assertSame(0, ShortLinkManager::$plugin->localCache->clearDeviceCache());
                self::assertNull($detector->getValue($service));
                self::assertTrue($device->get($deviceIdentity)->isMiss());
                self::assertSame('qr', $qr->get('owned')->value);
                self::assertSame('safe', $sentinel->get('owned')->value);
            });
        } finally {
            Craft::$app->set('cache', $originalCache);
            Craft::$app->set('request', $originalRequest);
            ShortLinkManager::$plugin->set('deviceDetection', DeviceDetectionService::class);
        }
    }

    public function testEphemeralUnsuitableCacheDisablesDeviceCachingWithoutResolvingAFilePath(): void
    {
        $originalCache = Craft::$app->getCache();
        self::assertInstanceOf(CacheInterface::class, $originalCache);
        $originalEphemeral = $_SERVER['CRAFT_EPHEMERAL'] ?? null;
        $hadEphemeral = array_key_exists('CRAFT_EPHEMERAL', $_SERVER);
        Craft::$app->set('cache', new \yii\caching\ArrayCache());
        $_SERVER['CRAFT_EPHEMERAL'] = true;

        try {
            $this->withSettings([
                'cacheStorageMethod' => 'file',
                'cacheDeviceDetection' => true,
            ], function(): void {
                $service = new DeviceDetectionService();
                $config = (new \ReflectionMethod($service, 'getDeviceDetectionConfig'))->invoke($service);
                self::assertIsArray($config);
                self::assertFalse($config['cacheEnabled']);
                self::assertSame('file', $config['cacheStorageMethod']);
                self::assertNull($config['cachePath']);
                self::assertDirectoryDoesNotExist(Craft::$app->getRuntimePath() . '/shortlink-manager');
            });
        } finally {
            Craft::$app->set('cache', $originalCache);
            if ($hadEphemeral) {
                $_SERVER['CRAFT_EPHEMERAL'] = $originalEphemeral;
            } else {
                unset($_SERVER['CRAFT_EPHEMERAL']);
            }
        }
    }

    public function testClearAllInvalidatesQrAndDeviceFamiliesWithoutChangingAnUnrelatedSentinel(): void
    {
        $originalCache = Craft::$app->getCache();
        self::assertInstanceOf(CacheInterface::class, $originalCache);
        $cache = new CascadeCache();
        Craft::$app->set('cache', $cache);

        try {
            $this->withSettings(['cacheStorageMethod' => 'craft'], function() use ($cache): void {
                ShortLinkManager::$plugin->set('deviceDetection', new DeviceDetectionService());
                $qr = new ScopedCache($cache, ShortLinkManager::$plugin->id, CacheStorageService::FAMILY_QR);
                $device = new ScopedCache($cache, ShortLinkManager::$plugin->id, CacheStorageService::FAMILY_DEVICE);
                $sentinel = new ScopedCache($cache, 'unrelated-plugin', 'sentinel');
                self::assertTrue($qr->set('owned', 'qr', 60));
                self::assertTrue($device->set('owned', 'device', 60));
                self::assertTrue($sentinel->set('owned', 'safe', 60));

                $decision = ShortLinkManager::$plugin->cacheStorage->getStorageDecision();
                self::assertSame(0, ShortLinkManager::$plugin->localCache->clearAllCaches($decision));
                self::assertTrue($qr->get('owned')->isMiss());
                self::assertTrue($device->get('owned')->isMiss());
                self::assertSame('safe', $sentinel->get('owned')->value);
            });
        } finally {
            Craft::$app->set('cache', $originalCache);
            ShortLinkManager::$plugin->set('deviceDetection', DeviceDetectionService::class);
        }
    }

    public function testDurableDeviceFileCachePreservesBaseIdentityJsonTtlAndClearBehavior(): void
    {
        $originalRequest = Craft::$app->getRequest();
        $hadEphemeral = array_key_exists('CRAFT_EPHEMERAL', $_SERVER);
        $originalEphemeral = $_SERVER['CRAFT_EPHEMERAL'] ?? null;
        $_SERVER['CRAFT_EPHEMERAL'] = false;
        Craft::$app->set('request', new class() extends \craft\console\Request {
            public function getQueryParam($name, $defaultValue = null): mixed
            {
                return $defaultValue;
            }

            public function getHeaders(): \yii\web\HeaderCollection
            {
                return new \yii\web\HeaderCollection();
            }
        });

        try {
            $this->withSettings([
                'cacheStorageMethod' => 'file',
                'cacheDeviceDetection' => true,
                'deviceDetectionCacheDuration' => 60,
            ], function(): void {
                $userAgent = 'ShortlinkDurableDevice/1.0';
                $path = PluginHelper::getCachePath(ShortLinkManager::$plugin, CacheStorageService::FAMILY_DEVICE);
                $file = $path . md5($userAgent) . '.cache';
                $detected = (new DeviceDetectionService())->detectDevice($userAgent);

                self::assertFileExists($file);
                self::assertSame($detected, json_decode((string)file_get_contents($file), true));
                self::assertSame(1, ShortLinkManager::$plugin->cacheStorage->countFiles(CacheStorageService::FAMILY_DEVICE));
                touch($file, time() - 61);
                $refreshed = (new DeviceDetectionService())->detectDevice($userAgent);
                self::assertSame($detected, $refreshed);
                self::assertGreaterThan(time() - 10, filemtime($file));
                self::assertSame(1, ShortLinkManager::$plugin->localCache->clearDeviceCache());
                self::assertFileDoesNotExist($file);
            });
        } finally {
            Craft::$app->set('request', $originalRequest);
            if ($hadEphemeral) {
                $_SERVER['CRAFT_EPHEMERAL'] = $originalEphemeral;
            } else {
                unset($_SERVER['CRAFT_EPHEMERAL']);
            }
        }
    }
}
