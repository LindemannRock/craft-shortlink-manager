<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * @since 5.21.1
 */
#[CoversNothing]
class SeomaticTrackingTemplateTest extends TestCase
{
    public function testSeomaticTrackingTemplateRendersDirectly(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__, 2) . '/src/templates/_integrations/seomatic.twig');

        $this->assertStringNotContainsString('{% macro', $template);
        $this->assertStringNotContainsString('{% endmacro %}', $template);
        $this->assertStringContainsString('code: link.code', $template);
        $this->assertStringContainsString('title: link.title', $template);
        $this->assertStringNotContainsString('shortLink.code', $template);
        $this->assertStringContainsString('window.dataLayer.push({{ eventDataJson|raw }});', $template);
        $this->assertStringContainsString('eventData|json_encode', $template);
    }

    public function testPublicTemplatesUseIntentBasedSeomaticHelpers(): void
    {
        $templateDir = dirname(__DIR__, 2) . '/src/templates';
        $redirectTemplate = (string) file_get_contents($templateDir . '/redirect.twig');
        $qrTemplate = (string) file_get_contents($templateDir . '/qr.twig');

        $this->assertStringContainsString('renderRedirectSeomaticTracking()', $redirectTemplate);
        $this->assertStringContainsString('renderQrSeomaticTracking()', $qrTemplate);
        $this->assertStringNotContainsString('renderRedirectSeomaticTracking is defined', $redirectTemplate);
        $this->assertStringNotContainsString('renderQrSeomaticTracking is defined', $qrTemplate);
        $this->assertStringNotContainsString("renderSeomaticTracking('qr_scan')", $qrTemplate);
        $this->assertStringNotContainsString('renderSeomaticTracking(eventType)', $redirectTemplate);
        $this->assertStringNotContainsString('DEBUG MODE', $qrTemplate);
        $this->assertStringNotContainsString('debugMode', $qrTemplate);
    }

    public function testQrTemplatesKeepPublicUrlsCanonicalAndDownloadsAuthenticated(): void
    {
        $templateDir = dirname(__DIR__, 2) . '/src/templates';
        $qrTemplate = (string)file_get_contents($templateDir . '/qr.twig');
        $editTemplate = (string)file_get_contents($templateDir . '/shortlinks/edit.twig');
        $sidebarTemplate = (string)file_get_contents($templateDir . '/_sidebars/shortlink-info.twig');

        self::assertStringContainsString('shortLink.getQrCodeUrl()', $qrTemplate);
        self::assertStringNotContainsString('shortLink.getQrCodeUrl({', $qrTemplate);
        self::assertStringContainsString("qrDownloadUrl: shortLink.id ? actionUrl('shortlink-manager/qr-code/generate'", $editTemplate);
        self::assertStringContainsString('siteId: shortLink.siteId', $editTemplate);
        self::assertStringNotContainsString('qrPublicBaseUrl:', $editTemplate);
        self::assertSame(4, substr_count($sidebarTemplate, 'download: 1'));
        self::assertStringNotContainsString('getQrCodeUrl({ format:', $sidebarTemplate);
    }
}
