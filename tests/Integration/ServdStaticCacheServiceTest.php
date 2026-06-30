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
}
