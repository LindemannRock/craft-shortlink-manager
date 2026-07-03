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
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins Servd static-cache purge URL generation for custom-domain shortlinks.
 *
 * @since 5.25.0
 */
final class ServdStaticCacheServiceTest extends TestCase
{
    private const SERVD_ENV_VARS = [
        'SERVD_CACHE_ENABLED',
        'REDIS_STATIC_CACHE_DB',
        'REDIS_HOST',
        'REDIS_PORT',
        'ENVIRONMENT',
        'SERVD_PROJECT_SLUG',
    ];

    public function testIsAvailableReturnsFalseWithoutServdRuntimeEnvironment(): void
    {
        $this->withoutServdRuntimeEnvironment(function(): void {
            self::assertFalse(ShortLinkManager::$plugin->servdStaticCache->isAvailable());
        });
    }

    public function testPurgeUrlsUseConfiguredBaseUrlAndEnabledSites(): void
    {
        $sites = Craft::$app->getSites()->getAllSites();
        self::assertNotEmpty($sites);

        $enabledSiteIds = array_map(static fn($site): int => (int)$site->id, $sites);
        $expectedUrls = [];

        foreach ($sites as $site) {
            $expectedUrls[] = 'https://short.example/' . $site->handle . '/s/sl-test-servd-cache';
            $expectedUrls[] = 'https://short.example/' . $site->handle . '/s/qr/sl-test-servd-cache/view';
        }

        $this->withSettings([
            'enabledSites' => $enabledSiteIds,
            'shortlinkBaseUrl' => 'https://short.example/{siteHandle}',
            'usePrefix' => true,
            'slugPrefix' => 's',
            'qrPrefix' => 's/qr',
        ], function() use ($expectedUrls): void {
            $urls = ShortLinkManager::$plugin->servdStaticCache->urlsForSlug('sl-test-servd-cache');

            sort($expectedUrls);
            sort($urls);

            self::assertSame($expectedUrls, $urls);
        });
    }

    public function testPurgeUrlsRespectRootShortlinks(): void
    {
        $primarySite = Craft::$app->getSites()->getPrimarySite();

        $this->withSettings([
            'enabledSites' => [$primarySite->id],
            'shortlinkBaseUrl' => 'https://short.example',
            'usePrefix' => false,
            'slugPrefix' => 's',
            'qrPrefix' => 'qr',
        ], function(): void {
            self::assertSame([
                'https://short.example/sl-test-servd-root',
                'https://short.example/qr/sl-test-servd-root/view',
            ], ShortLinkManager::$plugin->servdStaticCache->urlsForSlug('sl-test-servd-root'));
        });
    }

    public function testPurgeAllSourceUsesAvailabilityCheckAndPagedSlugIteration(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $source = file_get_contents($pluginRoot . '/src/services/ServdStaticCacheService.php');

        self::assertIsString($source);
        self::assertStringContainsString('if (!$this->isAvailable())', $source);
        self::assertStringContainsString('foreach ($this->eachSlug() as $slug)', $source);
        self::assertStringContainsString('->batch(500)', $source);
        self::assertStringContainsString('PURGE_URL_BATCH_SIZE = 500', $source);
        self::assertStringNotContainsString('function allSlugs', $source);
        self::assertStringNotContainsString('->column()', $source);
    }

    public function testIsAvailableSourceMatchesServdRuntimeGuard(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $source = file_get_contents($pluginRoot . '/src/services/ServdStaticCacheService.php');

        self::assertIsString($source);
        self::assertStringContainsString('use craft\helpers\App;', $source);
        self::assertStringContainsString("PluginHelper::isPluginEnabled(self::SERVD_PLUGIN_HANDLE)", $source);
        self::assertStringContainsString('class_exists(self::PURGE_URLS_JOB)', $source);
        self::assertStringContainsString('class_exists(self::STATIC_CACHE)', $source);
        self::assertStringContainsString("extension_loaded('redis')", $source);

        foreach (self::SERVD_ENV_VARS as $name) {
            self::assertStringContainsString("'$name'", $source);
        }

        self::assertStringContainsString('App::env($name)', $source);
        self::assertStringContainsString("in_array(App::env('ENVIRONMENT'), ['development', 'staging', 'production'], true)", $source);
        self::assertStringNotContainsString('getenv(', $source);
    }

    public function testRuntimeConfigRequiresRealServdEnvironment(): void
    {
        $method = new \ReflectionMethod(ShortLinkManager::$plugin->servdStaticCache, 'hasRequiredRuntimeConfig');

        foreach ([null, '', 'dev', 'test', 'testing', 'local'] as $environment) {
            $this->withServdRuntimeEnvironment($environment, function() use ($method): void {
                self::assertFalse($method->invoke(ShortLinkManager::$plugin->servdStaticCache));
            });
        }

        foreach (['development', 'staging', 'production'] as $environment) {
            $this->withServdRuntimeEnvironment($environment, function() use ($method): void {
                self::assertTrue($method->invoke(ShortLinkManager::$plugin->servdStaticCache));
            });
        }
    }

    public function testUtilityAndControllerSourcesWireDedicatedServdStaticCachePurge(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $utilitySource = file_get_contents($pluginRoot . '/src/utilities/ShortLinkManagerUtility.php');
        $templateSource = file_get_contents($pluginRoot . '/src/templates/utilities/index.twig');
        $controllerSource = file_get_contents($pluginRoot . '/src/controllers/SettingsController.php');

        self::assertIsString($utilitySource);
        self::assertIsString($templateSource);
        self::assertIsString($controllerSource);

        self::assertStringContainsString("'servdStaticCacheAvailable' => ShortLinkManager::\$plugin->servdStaticCache->isAvailable()", $utilitySource);
        self::assertStringContainsString("'linksName' => \$settings->getPluralLowerDisplayName()", $utilitySource);

        self::assertStringContainsString('hasServdStaticCacheManagement = servdStaticCacheAvailable and canClearCache', $templateSource);
        self::assertStringContainsString('Servd Static Cache', $templateSource);
        self::assertStringContainsString('purge-servd-static-cache', $templateSource);
        self::assertStringContainsString("actionUrl('shortlink-manager/settings/purge-servd-static-cache')", $templateSource);
        self::assertStringNotContainsString('servdStaticCache->isAvailable()', $templateSource);

        self::assertStringContainsString('function actionPurgeServdStaticCache()', $controllerSource);
        self::assertStringContainsString('$this->requirePostRequest();', $controllerSource);
        self::assertStringContainsString('$this->requirePermission(\'shortLinkManager:clearCache\');', $controllerSource);
        self::assertStringContainsString('$this->requireAcceptsJson();', $controllerSource);
        self::assertStringContainsString('ShortLinkManager::$plugin->servdStaticCache->isAvailable()', $controllerSource);
        self::assertStringContainsString('ShortLinkManager::$plugin->servdStaticCache->purgeAllUrls();', $controllerSource);
        self::assertStringContainsString('Servd static cache purge queued.', $controllerSource);
        self::assertStringContainsString('Servd static cache is not available.', $controllerSource);
    }

    public function testServdStaticCacheControllerActionDoesNotClearLocalOrGlobalCaches(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $controllerSource = file_get_contents($pluginRoot . '/src/controllers/SettingsController.php');
        self::assertIsString($controllerSource);

        $start = strpos($controllerSource, 'public function actionPurgeServdStaticCache(): Response');
        self::assertIsInt($start);

        $end = strpos($controllerSource, 'public function actionClearAllAnalytics(): Response', $start);
        self::assertIsInt($end);

        $actionSource = substr($controllerSource, $start, $end - $start);
        self::assertStringContainsString('purgeAllUrls()', $actionSource);
        self::assertStringNotContainsString('localCache->clearAllCaches()', $actionSource);
        self::assertStringNotContainsString('localCache->clearQrCache()', $actionSource);
        self::assertStringNotContainsString('localCache->clearDeviceCache()', $actionSource);
        self::assertStringNotContainsString('Craft::$app->getCache()->flush', $actionSource);
        self::assertStringNotContainsString('Craft::$app->cache->flush', $actionSource);
    }

    private function withoutServdRuntimeEnvironment(callable $callback): void
    {
        $previousServer = [];
        $previousEnv = [];

        foreach (self::SERVD_ENV_VARS as $name) {
            $previousServer[$name] = array_key_exists($name, $_SERVER) ? $_SERVER[$name] : null;
            $previousEnv[$name] = getenv($name);
            unset($_SERVER[$name]);
            putenv($name);
        }

        try {
            $callback();
        } finally {
            foreach (self::SERVD_ENV_VARS as $name) {
                if ($previousServer[$name] === null) {
                    unset($_SERVER[$name]);
                } else {
                    $_SERVER[$name] = $previousServer[$name];
                }

                if ($previousEnv[$name] === false) {
                    putenv($name);
                } else {
                    putenv($name . '=' . $previousEnv[$name]);
                }
            }
        }
    }

    private function withServdRuntimeEnvironment(?string $environment, callable $callback): void
    {
        $this->withServdEnvVars([
            'SERVD_CACHE_ENABLED' => '1',
            'REDIS_STATIC_CACHE_DB' => '1',
            'REDIS_HOST' => 'redis',
            'REDIS_PORT' => '6379',
            'ENVIRONMENT' => $environment,
            'SERVD_PROJECT_SLUG' => 'test-project',
        ], $callback);
    }

    /**
     * @param array<string, string|null> $values
     */
    private function withServdEnvVars(array $values, callable $callback): void
    {
        $previousServer = [];
        $previousEnv = [];

        foreach (self::SERVD_ENV_VARS as $name) {
            $previousServer[$name] = array_key_exists($name, $_SERVER) ? $_SERVER[$name] : null;
            $previousEnv[$name] = getenv($name);

            if (($values[$name] ?? null) === null) {
                unset($_SERVER[$name]);
                putenv($name);
                continue;
            }

            $_SERVER[$name] = $values[$name];
            putenv($name . '=' . $values[$name]);
        }

        try {
            $callback();
        } finally {
            foreach (self::SERVD_ENV_VARS as $name) {
                if ($previousServer[$name] === null) {
                    unset($_SERVER[$name]);
                } else {
                    $_SERVER[$name] = $previousServer[$name];
                }

                if ($previousEnv[$name] === false) {
                    putenv($name);
                } else {
                    putenv($name . '=' . $previousEnv[$name]);
                }
            }
        }
    }
}
