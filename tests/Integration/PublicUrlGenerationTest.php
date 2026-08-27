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
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins public shortlink and QR URL generation for prefix and custom-domain
 * setups, including the headless/custom-domain regressions from GH #28/#43.
 *
 * @since 5.20.0
 */
final class PublicUrlGenerationTest extends TestCase
{
    public function testShortlinkUrlUsesConfiguredPrefixByDefault(): void
    {
        $link = $this->seedShortLink([
            'code' => 'sl-test-url-prefix',
            'slug' => 'sl-test-url-prefix',
        ]);

        $this->withSettings([
            'shortlinkBaseUrl' => null,
            'usePrefix' => true,
            'slugPrefix' => 's',
        ], function() use ($link): void {
            self::assertSame('/s/sl-test-url-prefix', (string) parse_url($link->getUrl(), PHP_URL_PATH));
        });
    }

    public function testShortlinkUrlCanOmitPrefix(): void
    {
        $link = $this->seedShortLink([
            'code' => 'sl-test-url-root',
            'slug' => 'sl-test-url-root',
        ]);

        $this->withSettings([
            'shortlinkBaseUrl' => null,
            'usePrefix' => false,
            'slugPrefix' => 's',
        ], function() use ($link): void {
            self::assertSame('/sl-test-url-root', (string) parse_url($link->getUrl(), PHP_URL_PATH));
        });
    }

    public function testShortlinkUrlUsesCustomDomainWithAndWithoutPrefix(): void
    {
        $link = $this->seedShortLink([
            'code' => 'sl-test-custom-domain',
            'slug' => 'sl-test-custom-domain',
        ]);

        $this->withSettings([
            'shortlinkBaseUrl' => 'https://short.example',
            'usePrefix' => true,
            'slugPrefix' => 'go',
        ], function() use ($link): void {
            self::assertSame('https://short.example/go/sl-test-custom-domain', $link->getUrl());
        });

        $this->withSettings([
            'shortlinkBaseUrl' => 'https://short.example',
            'usePrefix' => false,
            'slugPrefix' => 'go',
        ], function() use ($link): void {
            self::assertSame('https://short.example/sl-test-custom-domain', $link->getUrl());
        });
    }

    public function testCustomDomainExpandsSiteTokens(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $link = $this->seedShortLink([
            'code' => 'sl-test-token-domain',
            'slug' => 'sl-test-token-domain',
            'siteId' => $site->id,
        ]);

        $this->withSettings([
            'shortlinkBaseUrl' => 'https://short.example/{siteHandle}/{siteId}/{siteUid}',
            'usePrefix' => true,
            'slugPrefix' => 's',
        ], function() use ($link, $site): void {
            self::assertSame(
                "https://short.example/{$site->handle}/{$site->id}/{$site->uid}/s/sl-test-token-domain",
                $link->getUrl(),
            );
        });
    }

    public function testQrUrlsUsePublicCustomDomainAndDownloadParameter(): void
    {
        $link = $this->seedShortLink([
            'code' => 'sl-test-qr-domain',
            'slug' => 'sl-test-qr-domain',
        ]);

        $this->withSettings([
            'shortlinkBaseUrl' => 'https://short.example',
            'qrPrefix' => 's/qr',
        ], function() use ($link): void {
            $imageUrl = $link->getQrCodeUrl();
            self::assertSame('https://short.example/s/qr/sl-test-qr-domain', $imageUrl);

            $downloadUrl = $link->getQrCodeUrl(['format' => 'png', 'size' => 512, 'download' => 1]);
            self::assertSame('https://short.example/s/qr/sl-test-qr-domain?download=1', $downloadUrl);
            self::assertStringNotContainsString('/actions/', $downloadUrl);

            self::assertSame(
                'https://short.example/s/qr/sl-test-qr-domain/view',
                $link->getQrCodeDisplayUrl(['format' => 'svg', 'size' => 999]),
            );
        });
    }

    public function testQrUrlsSupportStandaloneQrPrefix(): void
    {
        $link = $this->seedShortLink([
            'code' => 'sl-test-qr-standalone',
            'slug' => 'sl-test-qr-standalone',
        ]);

        $this->withSettings([
            'shortlinkBaseUrl' => 'https://short.example',
            'qrPrefix' => 'qr',
        ], function() use ($link): void {
            self::assertSame('https://short.example/qr/sl-test-qr-standalone', $link->getQrCodeUrl());
            self::assertSame('https://short.example/qr/sl-test-qr-standalone/view', $link->getQrCodeDisplayUrl());
        });
    }
}
