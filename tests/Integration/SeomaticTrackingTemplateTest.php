<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\integrations\IntegrationInterface;
use lindemannrock\shortlinkmanager\integrations\SeomaticIntegration;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\services\IntegrationService;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @since 5.21.1
 */
#[CoversNothing]
class SeomaticTrackingTemplateTest extends TestCase
{
    public function testNormalNavigationRecordsRedirectOnlyAtTheTimerBoundary(): void
    {
        $result = $this->executeTracking();
        self::assertSame(['already_queued'], $result['arrivalEvents']);
        self::assertSame(['short_links_redirect', 'navigate'], $result['timeline']);
        $this->assertNavigationTiming($result, true);
        self::assertSame(['https://links.example/ar/actions/shortlink-manager/redirect/go/campaign?site=ar'], $result['navigations']);
        self::assertSame('direct', $result['events'][1]['shortlink']['source']);
        self::assertSame('redirect', $result['events'][1]['shortlink']['click_type']);
        self::assertSame([['event' => 'short_links_redirect', 'time' => 100]], $result['eventTimes']);
    }

    public function testQrArrivalAndNavigationEmitIndependentEvents(): void
    {
        $result = $this->executeTracking(['search' => '?src=qr']);
        self::assertSame(['already_queued', 'short_links_qr_scan'], $result['arrivalEvents']);
        self::assertSame(['short_links_qr_scan', 'short_links_redirect', 'navigate'], $result['timeline']);
        self::assertSame('qr', $result['events'][1]['shortlink']['source']);
        self::assertSame('qr_scan', $result['events'][1]['shortlink']['click_type']);
        self::assertSame('qr', $result['events'][2]['shortlink']['source']);
        self::assertSame('redirect', $result['events'][2]['shortlink']['click_type']);
        $this->assertNavigationTiming($result, true);
        self::assertSame([0, 100], array_column($result['eventTimes'], 'time'));
    }

    #[DataProvider('pausedSources')]
    public function testDebugPauseRecordsOnlyQrArrival(string $search, array $expected): void
    {
        $result = $this->executeTracking(['search' => $search]);
        self::assertSame($expected, array_column($result['events'], 'event'));
        self::assertSame([], $result['navigations']);
        self::assertSame([], $result['timerDelays']);
        self::assertSame([0, 0, 0, 0, 0], array_map(static fn(array $point): int => count($point['navigations']), $result['checkpoints']));
    }

    public static function pausedSources(): iterable
    {
        yield 'direct' => ['?debug=1', ['already_queued']];
        yield 'qr' => ['?src=qr&debug=1', ['already_queued', 'short_links_qr_scan']];
    }

    public function testDebugParameterDoesNotPauseWhenOverrideIsDisallowed(): void
    {
        $result = $this->executeTracking(['search' => '?src=qr&debug=1', 'allowDebug' => false]);
        self::assertSame(['short_links_qr_scan', 'short_links_redirect', 'navigate'], $result['timeline']);
    }

    #[DataProvider('eventSelections')]
    public function testEventSwitchesIndependentlyControlArrivalAndNavigation(array $enabled, bool $tagged = true): void
    {
        $result = $this->executeTracking(['search' => $tagged ? '?src=qr' : ''], ['seomaticTrackingEvents' => $enabled]);
        $expected = ['already_queued'];
        if ($tagged && in_array('qr_scan', $enabled, true)) {
            $expected[] = 'short_links_qr_scan';
        }
        if (in_array('redirect', $enabled, true)) {
            $expected[] = 'short_links_redirect';
        }
        self::assertSame($expected, array_column($result['events'], 'event'));
        self::assertCount(1, $result['navigations']);
        $this->assertNavigationTiming($result, count($expected) > 1);
    }

    public static function eventSelections(): iterable
    {
        yield 'none' => [[]];
        yield 'redirect' => [['redirect']];
        yield 'qr' => [['qr_scan']];
        yield 'both' => [['redirect', 'qr_scan']];
        yield 'qr untagged' => [['qr_scan'], false];
    }

    #[DataProvider('configuredPrefixes')]
    public function testExplicitPrefixesAndEncodedPayloadsRemainUnchanged(string $prefix): void
    {
        $result = $this->executeTracking(['search' => '?src=qr'], ['seomaticEventPrefix' => $prefix]);
        self::assertSame(['already_queued', $prefix . '_qr_scan', $prefix . '_redirect'], array_column($result['events'], 'event'));
        self::assertSame("Campaign 'quoted' & </script> mobile", $result['events'][1]['shortlink']['title']);
        self::assertSame('campaign', $result['events'][1]['shortlink']['code']);
    }

    public static function configuredPrefixes(): iterable
    {
        yield 'legacy' => ['shortlink_manager'];
        yield 'custom' => ['custom_campaign'];
    }

    public function testCustomBrowserSourceIsPreservedWithoutCountingQrArrival(): void
    {
        $result = $this->executeTracking(['search' => '?src=unexpected']);
        self::assertSame(['short_links_redirect', 'navigate'], $result['timeline']);
        self::assertSame('unexpected', $result['events'][1]['shortlink']['source']);
    }

    public function testDisabledIntegrationDoesNotBlockNavigation(): void
    {
        $result = $this->executeTracking(['search' => '?src=qr'], ['enabledIntegrations' => []]);
        self::assertSame(['already_queued'], array_column($result['events'], 'event'));
        self::assertSame(['navigate'], $result['timeline']);
        $this->assertNavigationTiming($result, false);
    }

    public function testDisabledAnalyticsDoesNotBlockNavigation(): void
    {
        $result = $this->executeTracking(['search' => '?src=qr'], ['enableAnalytics' => false]);
        self::assertSame(['already_queued'], array_column($result['events'], 'event'));
        self::assertSame(['navigate'], $result['timeline']);
        $this->assertNavigationTiming($result, false);
    }

    public function testUnavailableSeomaticDoesNotBlockNavigation(): void
    {
        $this->swapPluginComponent('shortlink-manager', 'integration', new class() extends IntegrationService {
            public function getIntegration(string $handle): ?IntegrationInterface
            {
                return new class() extends SeomaticIntegration {
                    public function isAvailable(): bool
                    {
                        return false;
                    }
                };
            }
        });
        $result = $this->executeTracking(['search' => '?src=qr']);
        self::assertSame(['already_queued'], array_column($result['events'], 'event'));
        self::assertSame(['navigate'], $result['timeline']);
        $this->assertNavigationTiming($result, false);
    }

    public function testLegacyQrDisplayHelpersRemainCallableAndInert(): void
    {
        $this->withSettings(['enableAnalytics' => true, 'enabledIntegrations' => ['seomatic']], function(): void {
            $link = new ShortLink();
            self::assertNull($link->renderQrSeomaticTracking());
            self::assertNull($link->renderSeomaticTracking('qr_scan'));
        });
    }

    public function testPublicTemplatesUseLandingHelpersAndDoNotTrackQrDisplay(): void
    {
        $templateDir = dirname(__DIR__, 2) . '/src/templates';
        $redirect = (string)file_get_contents($templateDir . '/redirect.twig');
        $qr = (string)file_get_contents($templateDir . '/qr.twig');
        self::assertStringContainsString('renderRedirectSeomaticTracking()', $redirect);
        self::assertStringContainsString('renderRedirectScript()', $redirect);
        self::assertStringNotContainsString('renderQrSeomaticTracking()', $qr);
        self::assertStringNotContainsString("renderSeomaticTracking('qr_scan')", $qr);
    }

    private function assertNavigationTiming(array $result, bool $grace): void
    {
        self::assertSame([99, 100, 2099, 2100, 4100], array_column($result['checkpoints'], 'time'));
        self::assertSame($grace ? [0, 0, 0, 1, 1] : [0, 1, 1, 1, 1], array_map(static fn(array $point): int => count($point['navigations']), $result['checkpoints']));
        self::assertSame([$grace ? 2100 : 100], $result['navigationTimes']);
        self::assertSame($grace ? [100, 2000] : [100], $result['timerDelays']);
        self::assertSame($result['checkpoints'][1]['events'], $result['checkpoints'][4]['events']);
    }

    private function executeTracking(array $input = [], array $settings = []): array
    {
        return $this->withSettings(array_merge([
            'enableAnalytics' => true,
            'enabledIntegrations' => ['seomatic'],
            'seomaticEventPrefix' => (new Settings())->seomaticEventPrefix,
            'seomaticTrackingEvents' => ['redirect', 'qr_scan'],
        ], $settings), function() use ($input): array {
            $link = new ShortLink(['code' => 'campaign', 'title' => "Campaign 'quoted' & </script> mobile"]);
            $link->setRedirectScriptUrl('https://links.example/ar/actions/shortlink-manager/redirect/go/campaign?site=ar');
            $html = (string)$link->renderRedirectSeomaticTracking() . (string)$link->renderRedirectScript($input['allowDebug'] ?? true);
            self::assertNotSame('', $html);
            $process = new \Symfony\Component\Process\Process(['node', dirname(__DIR__) . '/js/run-seomatic-tracking.mjs']);
            $process->setInput(json_encode(array_merge(['checkpoints' => [99, 100, 2099, 2100, 4100]], $input, ['html' => $html]), JSON_THROW_ON_ERROR));
            $process->setTimeout(10);
            try {
                $process->run();
                self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
                return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            } finally {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
        });
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
