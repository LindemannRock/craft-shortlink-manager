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
use craft\web\View;
use lindemannrock\base\cache\CacheBackendStatus;
use lindemannrock\base\cache\DisposableCacheStorageDecision;
use lindemannrock\base\cache\DisposableCacheStoragePresenter;
use lindemannrock\shortlinkmanager\controllers\SettingsController;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\services\CacheStorageService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
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
final class CacheStoragePresentationTest extends TestCase
{
    private CacheInterface $originalCache;
    private bool $hadEphemeralSetting;
    private mixed $originalEphemeralSetting;

    protected function setUp(): void
    {
        parent::setUp();
        $cache = Craft::$app->getCache();
        self::assertInstanceOf(CacheInterface::class, $cache);
        $this->originalCache = $cache;
        $this->hadEphemeralSetting = array_key_exists('CRAFT_EPHEMERAL', $_SERVER);
        $this->originalEphemeralSetting = $_SERVER['CRAFT_EPHEMERAL'] ?? null;
        $_SERVER['CRAFT_EPHEMERAL'] = false;
    }

    protected function tearDown(): void
    {
        Craft::$app->set('cache', $this->originalCache);
        if ($this->hadEphemeralSetting) {
            $_SERVER['CRAFT_EPHEMERAL'] = $this->originalEphemeralSetting;
        } else {
            unset($_SERVER['CRAFT_EPHEMERAL']);
        }
        parent::tearDown();
    }

    public function testSharedPresentationDistinguishesEffectiveStorageWithoutEnumeratingApplicationEntries(): void
    {
        $presenter = new DisposableCacheStoragePresenter();

        Craft::$app->set('cache', new CascadeCache());
        $managed = $presenter->present((new CacheStorageService())->getStorageDecision('craft'));
        self::assertSame('Using managed cache', $managed->headingKey);
        self::assertSame('Managed cache', $managed->utilityDescriptionKey);

        Craft::$app->set('cache', new PresentationUnknownCache());
        $_SERVER['CRAFT_EPHEMERAL'] = true;
        $bestEffort = $presenter->present((new CacheStorageService())->getStorageDecision('file'));
        self::assertSame('Using application cache', $bestEffort->headingKey);
        self::assertSame('Best effort', $bestEffort->utilityValueKey);
        self::assertSame([
            'This host has an ephemeral filesystem, so the application cache is used automatically.',
            'Cross-request persistence could not be confirmed.',
        ], $bestEffort->explanationKeys);

        Craft::$app->set('cache', new ArrayCache());
        $disabled = $presenter->present((new CacheStorageService())->getStorageDecision('craft'));
        self::assertSame('Caching disabled', $disabled->headingKey);
        self::assertSame('Recomputed as needed', $disabled->utilityDescriptionKey);
    }

    public function testKnownBackendsRenderAccurateSharedStatusTerms(): void
    {
        $redis = (new \ReflectionClass(RedisCache::class))->newInstanceWithoutConstructor();
        $cases = [
            [$redis, CacheBackendStatus::BACKEND_REDIS, 'Using Redis cache', 'Redis cache'],
            [new CascadeCache(), CacheBackendStatus::BACKEND_MANAGED, 'Using managed cache', 'Managed cache'],
            [new DbCache(), CacheBackendStatus::BACKEND_DATABASE, 'Using database cache', 'Database cache'],
            [new FileCache(), CacheBackendStatus::BACKEND_FILESYSTEM, 'Using filesystem cache', 'Filesystem cache'],
        ];

        foreach ($cases as [$cache, $backend, $heading, $description]) {
            self::assertInstanceOf(CacheInterface::class, $cache);
            Craft::$app->set('cache', $cache);
            $_SERVER['CRAFT_EPHEMERAL'] = false;
            $decision = (new CacheStorageService())->getStorageDecision('craft');
            $presentation = (new DisposableCacheStoragePresenter())->present($decision);
            self::assertTrue($decision->usesApplicationCache());
            self::assertSame($backend, $decision->backendStatus->backend);
            self::assertSame($heading, $presentation->headingKey);
            self::assertSame($description, $presentation->utilityDescriptionKey);
        }

        Craft::$app->set('cache', new ArrayCache());
        $fileDecision = (new CacheStorageService())->getStorageDecision('file');
        self::assertSame(DisposableCacheStorageDecision::EFFECTIVE_FILE, $fileDecision->effectiveStorage);
        self::assertSame('File cache', (new DisposableCacheStoragePresenter())->present($fileDecision)->utilityDescriptionKey);
    }

    public function testSettingsUseSharedFieldWithTokenPreservationReadOnlyStateAndPathSuppression(): void
    {
        Craft::$app->set('cache', new CascadeCache());
        $controller = new SettingsController('settings', ShortLinkManager::$plugin);
        $method = new \ReflectionMethod($controller, 'cacheStorageTemplateVariables');

        foreach (['redis', 'craft'] as $token) {
            $variables = $method->invoke($controller, new Settings(['cacheStorageMethod' => $token]));
            self::assertIsArray($variables);
            self::assertSame($token, $variables['applicationToken']);
            self::assertStringContainsString('/shortlink-manager/cache/', $variables['filePath']);
            self::assertSame([], $variables['applicationPresentation']->explanationKeys);
        }

        $_SERVER['CRAFT_EPHEMERAL'] = true;
        $variables = $method->invoke($controller, new Settings(['cacheStorageMethod' => 'file']));
        self::assertIsArray($variables);
        self::assertNull($variables['filePath']);
        self::assertContains(
            'This host has an ephemeral filesystem, so the application cache is used automatically.',
            $variables['filePresentation']->explanationKeys,
        );

        $template = $this->readPluginFile('src/templates/settings/cache.twig');
        self::assertStringContainsString("'lindemannrock-base/_partials/field-cache-storage'", $template);
        self::assertStringNotContainsString('yii\\redis\\Cache', $template);
        self::assertStringNotContainsString('Redis Not Configured', $template);

        $shared = (string)file_get_contents(dirname(__DIR__, 3) . '/base/src/templates/_partials/field-cache-storage.twig');
        self::assertStringContainsString("label: 'File cache'|t('lindemannrock-base')", $shared);
        self::assertStringContainsString("label: 'Application cache'|t('lindemannrock-base')", $shared);
        self::assertStringContainsString('settings.isOverriddenByConfig(settingProperty)', $shared);
        self::assertStringContainsString('|namespaceInputId', $shared);
        self::assertStringContainsString('settings.getErrors(settingProperty)', $shared);
    }

    public function testSharedSettingsAndStatusTemplatesRenderForTheShortlinkConsumer(): void
    {
        Craft::$app->set('cache', new CascadeCache());
        $settings = new Settings(['cacheStorageMethod' => 'redis']);
        $settings->addError('cacheStorageMethod', 'Injected cache storage error.');
        $controller = new SettingsController('settings', ShortLinkManager::$plugin);
        $variables = (new \ReflectionMethod($controller, 'cacheStorageTemplateVariables'))->invoke($controller, $settings);
        self::assertIsArray($variables);

        $html = Craft::$app->getView()->renderTemplate(
            'lindemannrock-base/_partials/field-cache-storage',
            [
                'settings' => $settings,
                'pluginHandle' => ShortLinkManager::$plugin->id,
                'configuredStorageToken' => $settings->cacheStorageMethod,
                'applicationOptionToken' => $variables['applicationToken'],
                'filePresentation' => $variables['filePresentation'],
                'applicationPresentation' => $variables['applicationPresentation'],
                'filePath' => $variables['filePath'],
            ],
            View::TEMPLATE_MODE_CP,
        );

        self::assertStringContainsString('Cache Storage Method', $html);
        self::assertStringContainsString('value="file"', $html);
        self::assertStringContainsString('value="redis" selected', $html);
        self::assertStringContainsString('Using managed cache', $html);
        self::assertStringContainsString('Injected cache storage error.', $html);
        self::assertStringNotContainsString('Redis Not Configured', $html);
    }

    public function testEverySharedPresentationMessageExistsInAllBaseCatalogues(): void
    {
        $keys = [
            'Cache Storage Method',
            'File cache',
            'Application cache',
            'Using managed cache',
            'Using Redis cache',
            'Using database cache',
            'Using filesystem cache',
            'Using file cache',
            'Using application cache',
            'Caching disabled',
            'This host has an ephemeral filesystem, so the application cache is used automatically.',
            'Cross-request persistence could not be confirmed.',
            'No suitable cross-request cache is available. Cache data is recomputed as needed.',
            'Managed cache',
            'Redis cache',
            'Database cache',
            'Filesystem cache',
            'Best effort',
            'Recomputed as needed',
        ];

        foreach (['en', 'de', 'fr', 'nl', 'es', 'ar', 'it', 'pt', 'ja', 'sv', 'da', 'no'] as $locale) {
            $catalogue = require dirname(__DIR__, 3) . "/base/src/translations/{$locale}/lindemannrock-base.php";
            self::assertIsArray($catalogue);
            foreach ($keys as $key) {
                self::assertArrayHasKey($key, $catalogue, "Missing {$locale} Base key: {$key}");
            }
        }
    }

    public function testUtilityUsesSharedSemanticStatusAndSuppressesApplicationCountsAndPaths(): void
    {
        $utility = $this->readPluginFile('src/utilities/ShortLinkManagerUtility.php');
        $template = $this->readPluginFile('src/templates/utilities/index.twig');

        self::assertStringContainsString('new DisposableCacheStoragePresenter()', $utility);
        self::assertStringContainsString('$cacheDecision->usesFileCache()', $utility);
        self::assertStringNotContainsString('PluginHelper::getCacheBasePath', $utility);
        self::assertStringContainsString("cacheStorage.utilityValueKey|t('lindemannrock-base')", $template);
        self::assertStringContainsString("cacheStorage.utilityDescriptionKey|t('lindemannrock-base')", $template);
        self::assertStringContainsString('showCacheCounts ? qrCacheFiles : null', $template);
        self::assertStringNotContainsString('storageMethod', $template);
        self::assertStringNotContainsString('cachePath', $template);
    }

    public function testSettingsPersistenceAcceptsAndRestoresFileRedisAndCraftTokens(): void
    {
        $original = Settings::loadFromDatabase();
        $originalToken = $original->cacheStorageMethod;
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            foreach (['file', 'redis', 'craft'] as $token) {
                $settings = Settings::loadFromDatabase();
                $settings->cacheStorageMethod = $token;
                self::assertTrue($settings->saveToDatabase(['cacheStorageMethod']));
                self::assertSame($token, Settings::loadFromDatabase()->cacheStorageMethod);
            }
        } finally {
            $transaction->rollBack();
            self::assertSame($originalToken, Settings::loadFromDatabase()->cacheStorageMethod);
        }
    }

    public function testServdStaticCachePathsRemainOutsidePortableDisposableCacheChanges(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $portableDiffFiles = [
            'src/services/CacheStorageService.php',
            'src/services/DeviceDetectionService.php',
            'src/services/LocalCacheService.php',
            'src/services/QrCodeService.php',
        ];

        foreach ($portableDiffFiles as $path) {
            $source = $this->readPluginFile($path);
            self::assertStringNotContainsString('purgeAllPublicShortLinkUrls', $source);
            self::assertStringNotContainsString('servdStaticCacheEnabled', $source);
        }
        $servd = (string)file_get_contents($pluginRoot . '/src/services/ServdStaticCacheService.php');
        self::assertStringContainsString('purgeAllUrls', $servd);
        self::assertStringContainsString('Servd', $servd);
    }

    private function readPluginFile(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source);

        return $source;
    }
}

/**
 * Unknown best-effort application-cache fixture.
 *
 * @since 5.29.0
 */
final class PresentationUnknownCache extends Cache
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
