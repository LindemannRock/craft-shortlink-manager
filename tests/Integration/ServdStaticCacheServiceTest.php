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
}
