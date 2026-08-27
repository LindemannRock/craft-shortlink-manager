<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use Craft;
use craft\cachecascade\CascadeCache;
use lindemannrock\base\cache\CacheBackendStatus;
use lindemannrock\base\cache\DisposableCacheStorageDecision;
use lindemannrock\base\cache\DisposableCacheStorageResolver;
use lindemannrock\base\cache\ScopedCache;
use lindemannrock\base\cache\ScopedCacheResult;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\shortlinkmanager\services\CacheStorageService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use yii\caching\ArrayCache;
use yii\caching\Cache;
use yii\caching\CacheInterface;
use yii\caching\DbCache;
use yii\caching\FileCache;
use yii\redis\Cache as RedisCache;

require_once dirname(__DIR__) . '/Fixtures/CascadeCache.php';

/**
 * @since 5.29.0
 */
#[CoversClass(CacheStorageService::class)]
final class PortableDisposableCacheTest extends TestCase
{
    private CacheInterface $originalCache;
    private string $originalRuntimePath;
    private bool $hadEphemeralSetting;
    private mixed $originalEphemeralSetting;

    protected function setUp(): void
    {
        parent::setUp();

        $cache = Craft::$app->getCache();
        self::assertInstanceOf(CacheInterface::class, $cache);
        $this->originalCache = $cache;
        $this->originalRuntimePath = Craft::$app->getRuntimePath();
        $this->hadEphemeralSetting = array_key_exists('CRAFT_EPHEMERAL', $_SERVER);
        $this->originalEphemeralSetting = $_SERVER['CRAFT_EPHEMERAL'] ?? null;
        $_SERVER['CRAFT_EPHEMERAL'] = false;
        Craft::$app->setRuntimePath($this->createTrackedTempDirectory('shortlink-cache-runtime-'));
    }

    protected function tearDown(): void
    {
        Craft::$app->set('cache', $this->originalCache);
        Craft::$app->setRuntimePath($this->originalRuntimePath);
        if ($this->hadEphemeralSetting) {
            $_SERVER['CRAFT_EPHEMERAL'] = $this->originalEphemeralSetting;
        } else {
            unset($_SERVER['CRAFT_EPHEMERAL']);
        }

        parent::tearDown();
    }

    public function testApprovedBaseCacheContractLoadsFromTheLocalApprovedSource(): void
    {
        $baseSource = $this->baseSourceRoot();

        foreach ([
            CacheBackendStatus::class,
            DisposableCacheStorageDecision::class,
            DisposableCacheStorageResolver::class,
            ScopedCache::class,
            ScopedCacheResult::class,
        ] as $class) {
            $file = (new \ReflectionClass($class))->getFileName();
            self::assertIsString($file);
            self::assertStringStartsWith($baseSource . DIRECTORY_SEPARATOR, realpath($file) ?: '');
        }

        $helper = new \ReflectionMethod(PluginHelper::class, 'getApplicationCacheOrLog');
        $helperFile = $helper->getFileName();
        self::assertIsString($helperFile);
        self::assertStringStartsWith($baseSource . DIRECTORY_SEPARATOR, realpath($helperFile) ?: '');
    }

    public function testBackendClassificationKeepsManagedCacheOpaqueAndRejectsUnsuitableCaches(): void
    {
        $redis = (new \ReflectionClass(RedisCache::class))->newInstanceWithoutConstructor();
        self::assertSame(CacheBackendStatus::BACKEND_REDIS, CacheBackendStatus::fromCache($redis)->backend);
        Craft::$app->set('cache', $redis);
        $redisDecision = (new CacheStorageService())->getStorageDecision('redis');
        self::assertTrue($redisDecision->usesApplicationCache());
        self::assertSame($redis, $redisDecision->applicationCache);
        self::assertSame(CacheBackendStatus::BACKEND_MANAGED, CacheBackendStatus::fromCache(new CascadeCache())->backend);
        $database = new PortableDatabaseCache();
        self::assertSame(CacheBackendStatus::BACKEND_DATABASE, CacheBackendStatus::fromCache($database)->backend);
        Craft::$app->set('cache', $database);
        self::assertTrue((new CacheStorageService())->getStorageDecision('craft')->usesApplicationCache());
        self::assertTrue((new CacheStorageService())->writeQrCode('database', 'database-value', 71));
        self::assertSame('database-value', (new CacheStorageService())->readQrCode('database', 71)->value);
        self::assertSame(CacheBackendStatus::BACKEND_FILESYSTEM, CacheBackendStatus::fromCache(new FileCache())->backend);
        self::assertSame(CacheBackendStatus::BACKEND_MEMORY, CacheBackendStatus::fromCache(new ArrayCache())->backend);
        self::assertSame(CacheBackendStatus::BACKEND_UNKNOWN, CacheBackendStatus::fromCache(new PortablePersistentCache())->backend);

        $managedSource = (string)file_get_contents($this->baseSourceRoot() . '/cache/CacheBackendStatus.php');
        self::assertStringNotContainsString('hiddenPrimary', $managedSource);
        self::assertStringNotContainsString('Reflection', $managedSource);
    }

    private function baseSourceRoot(): string
    {
        $source = (new \ReflectionClass(CacheBackendStatus::class))->getFileName();
        self::assertIsString($source);

        return dirname($source, 2);
    }

    public function testSavedApplicationTokensUseSuitableApplicationCacheWithFiniteTtl(): void
    {
        $cache = new PortablePersistentCache();
        Craft::$app->set('cache', $cache);

        foreach (['redis', 'craft'] as $token) {
            $this->withSettings(['cacheStorageMethod' => $token], function() use ($cache, $token): void {
                $service = new CacheStorageService();
                $identity = 'binary-' . $token;
                self::assertTrue($service->getStorageDecision()->usesApplicationCache());
                self::assertSame($cache, $service->getStorageDecision()->applicationCache);
                self::assertTrue($service->writeQrCode($identity, "\x00\x01" . $token, 73));
                $result = $service->readQrCode($identity, 73);
                self::assertTrue($result->isHit());
                self::assertSame("\x00\x01" . $token, $result->value);
            });
        }

        self::assertContains(73, $cache->setDurations);
        self::assertFalse((new CacheStorageService())->writeQrCode('zero', 'no', 0));
    }

    public function testFalseyBinaryMissAndFailureStatesRemainDistinct(): void
    {
        $cache = new PortablePersistentCache();
        Craft::$app->set('cache', $cache);
        $diagnostics = new \ReflectionProperty(CacheStorageService::class, 'loggedFailures');
        $originalDiagnostics = $diagnostics->getValue();
        $diagnostics->setValue(null, []);

        try {
            $this->withSettings(['cacheStorageMethod' => 'craft'], function() use ($cache, $diagnostics): void {
                $service = new CacheStorageService();
                self::assertTrue($service->readQrCode('missing', 60)->isMiss());
                self::assertTrue($service->writeQrCode('empty', '', 60));
                $empty = $service->readQrCode('empty', 60);
                self::assertTrue($empty->isHit());
                self::assertSame('', $empty->value);

                $cache->throwOperations = true;
                self::assertTrue($service->readQrCode('failure', 60)->isFailure());
                self::assertTrue($service->readQrCode('failure-again', 60)->isFailure());
                self::assertFalse($service->writeQrCode('failure', 'value', 60));
                self::assertSame([
                    'qr:read' => true,
                    'qr:write' => true,
                ], $diagnostics->getValue());
            });
        } finally {
            $diagnostics->setValue(null, $originalDiagnostics);
        }
    }

    public function testEphemeralFileRoutesToSuitableCacheWithoutTouchingRuntimeFiles(): void
    {
        $cache = new PortablePersistentCache();
        Craft::$app->set('cache', $cache);
        $_SERVER['CRAFT_EPHEMERAL'] = true;
        $runtimePath = Craft::$app->getRuntimePath();
        $pluginCachePath = $runtimePath . '/shortlink-manager';

        $this->withSettings(['cacheStorageMethod' => 'file'], function() use ($pluginCachePath): void {
            $service = new CacheStorageService();
            $decision = $service->getStorageDecision();
            self::assertTrue($decision->usesApplicationCache());
            self::assertTrue($decision->fileStorageBypassed);
            self::assertFalse($decision->filePathEligible);
            self::assertNull($service->getDisplayFilePath($decision));
            self::assertTrue($service->writeQrCode('ephemeral', "\x00binary", 67));
            self::assertSame("\x00binary", $service->readQrCode('ephemeral', 67)->value);
            self::assertSame(0, $service->countFiles(CacheStorageService::FAMILY_QR, $decision));
            self::assertSame(0, $service->clearFamily(CacheStorageService::FAMILY_QR));
            self::assertDirectoryDoesNotExist($pluginCachePath);
        });
    }

    public function testEphemeralUnsuitableMissingAndThrowingCachesDisableStorage(): void
    {
        $_SERVER['CRAFT_EPHEMERAL'] = true;

        foreach ([new ArrayCache(), new FileCache()] as $cache) {
            Craft::$app->set('cache', $cache);
            $this->withSettings(['cacheStorageMethod' => 'file'], function(): void {
                $service = new CacheStorageService();
                self::assertTrue($service->getStorageDecision()->isDisabled());
                self::assertTrue($service->readQrCode('disabled', 60)->isMiss());
                self::assertFalse($service->writeQrCode('disabled', 'value', 60));
            });
        }

        Craft::$app->set('cache', static function(): never {
            throw new \RuntimeException('Injected cache resolution failure.');
        });
        $this->withSettings(['cacheStorageMethod' => 'craft'], function(): void {
            self::assertTrue((new CacheStorageService())->getStorageDecision()->isDisabled());
        });

        $this->withSettings(['cacheStorageMethod' => 'unknown'], function(): void {
            self::assertTrue((new CacheStorageService())->getStorageDecision()->isDisabled());
        });
        $_SERVER['CRAFT_EPHEMERAL'] = false;
        $this->withSettings(['cacheStorageMethod' => 'unknown'], function(): void {
            self::assertTrue((new CacheStorageService())->getStorageDecision()->isDisabled());
        });
    }

    public function testDurableQrFileContractPreservesPathNameBinaryMtimeAndClearBoundary(): void
    {
        $this->withSettings(['cacheStorageMethod' => 'file'], function(): void {
            $service = new CacheStorageService();
            $identity = 'shortlink-manager:qr:' . md5('complete-identity');
            $payload = "\x89PNG\r\n\x1a\n\x00raw";
            $path = PluginHelper::getCachePath(ShortLinkManager::$plugin, CacheStorageService::FAMILY_QR);
            $file = $path . md5($identity) . '.cache';
            $sentinel = $path . 'sentinel.txt';

            self::assertTrue($service->getStorageDecision()->usesFileCache());
            self::assertTrue($service->writeQrCode($identity, $payload, 30));
            self::assertSame($payload, file_get_contents($file));
            self::assertSame($payload, $service->readQrCode($identity, 30)->value);
            file_put_contents($sentinel, 'keep');
            self::assertSame(1, $service->countFiles(CacheStorageService::FAMILY_QR));

            touch($file, time() - 31);
            self::assertTrue($service->readQrCode($identity, 30)->isMiss());
            self::assertFileDoesNotExist($file);
            self::assertFileExists($sentinel);

            self::assertTrue($service->writeQrCode($identity, $payload, 30));
            self::assertSame(1, $service->clearFamily(CacheStorageService::FAMILY_QR));
            self::assertFileDoesNotExist($file);
            self::assertSame('keep', file_get_contents($sentinel));
        });
    }

    public function testGenerationClearsIsolateFamiliesAndPreserveUnrelatedEntries(): void
    {
        $cache = new PortablePersistentCache();
        Craft::$app->set('cache', $cache);

        $this->withSettings(['cacheStorageMethod' => 'craft'], function() use ($cache): void {
            $service = new CacheStorageService();
            $device = new ScopedCache($cache, ShortLinkManager::$plugin->id, CacheStorageService::FAMILY_DEVICE);
            $sentinel = new ScopedCache($cache, 'unrelated-plugin', 'sentinel');
            self::assertTrue($service->writeQrCode('qr', 'qr-value', 60));
            self::assertTrue($device->set('device', ['device' => true], 60));
            self::assertTrue($sentinel->set('keep', 'safe', 60));

            self::assertSame(0, $service->clearFamily(CacheStorageService::FAMILY_QR));
            self::assertTrue($service->readQrCode('qr', 60)->isMiss());
            self::assertSame(['device' => true], $device->get('device')->value);
            self::assertSame('safe', $sentinel->get('keep')->value);

            self::assertSame(0, $service->clearFamily(CacheStorageService::FAMILY_DEVICE));
            self::assertTrue($device->get('device')->isMiss());
            self::assertSame('safe', $sentinel->get('keep')->value);
            self::assertSame(0, $cache->flushCalls);
        });
    }

    public function testDurableFilesystemFailuresFailSoftlyWithoutEscaping(): void
    {
        $blockedRoot = Craft::$app->getRuntimePath() . '/shortlink-manager';
        file_put_contents($blockedRoot, 'not-a-directory');

        $this->withSettings(['cacheStorageMethod' => 'file'], function(): void {
            $service = new CacheStorageService();
            self::assertTrue($service->readQrCode('blocked', 60)->isMiss());
            self::assertFalse($service->writeQrCode('blocked', 'value', 60));
            self::assertSame(0, $service->countFiles(CacheStorageService::FAMILY_QR));
            self::assertSame(0, $service->clearFamily(CacheStorageService::FAMILY_QR));
        });
    }
}

/**
 * Best-effort persistent cache fixture.
 *
 * @since 5.29.0
 */
class PortablePersistentCache extends Cache
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var list<int> */
    public array $setDurations = [];

    public int $flushCalls = 0;
    public bool $throwOperations = false;

    public function set($key, $value, $duration = null, $dependency = null)
    {
        $this->setDurations[] = (int)$duration;

        return parent::set($key, $value, $duration, $dependency);
    }

    protected function getValue($key)
    {
        if ($this->throwOperations) {
            throw new \RuntimeException('Injected cache read failure.');
        }

        return $this->values[$key] ?? false;
    }

    protected function getValues($keys)
    {
        return array_map(fn(string $key): mixed => $this->getValue($key), $keys);
    }

    protected function setValue($key, $value, $duration)
    {
        if ($this->throwOperations) {
            throw new \RuntimeException('Injected cache write failure.');
        }
        $this->values[$key] = $value;

        return true;
    }

    protected function setValues($data, $duration)
    {
        foreach ($data as $key => $value) {
            $this->setValue($key, $value, $duration);
        }

        return [];
    }

    protected function addValue($key, $value, $duration)
    {
        if ($this->throwOperations) {
            throw new \RuntimeException('Injected cache add failure.');
        }
        if (array_key_exists($key, $this->values)) {
            return false;
        }
        $this->values[$key] = $value;

        return true;
    }

    protected function deleteValue($key)
    {
        unset($this->values[$key]);

        return true;
    }

    protected function flushValues()
    {
        $this->flushCalls++;
        $this->values = [];

        return true;
    }
}

/**
 * Database-cache-compatible fixture without database side effects.
 *
 * @since 5.29.0
 */
final class PortableDatabaseCache extends DbCache
{
    /** @var array<string, mixed> */
    private array $values = [];

    protected function getValue($key)
    {
        return $this->values[$key] ?? false;
    }

    protected function getValues($keys)
    {
        return array_map(fn(string $key): mixed => $this->getValue($key), $keys);
    }

    protected function setValue($key, $value, $duration)
    {
        $this->values[$key] = $value;

        return true;
    }

    protected function setValues($data, $duration)
    {
        foreach ($data as $key => $value) {
            $this->values[$key] = $value;
        }

        return [];
    }

    protected function addValue($key, $value, $duration)
    {
        if (array_key_exists($key, $this->values)) {
            return false;
        }
        $this->values[$key] = $value;

        return true;
    }

    protected function deleteValue($key)
    {
        unset($this->values[$key]);

        return true;
    }

    protected function flushValues()
    {
        $this->values = [];

        return true;
    }
}
