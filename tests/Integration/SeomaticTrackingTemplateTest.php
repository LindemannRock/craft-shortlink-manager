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
use craft\db\Query;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\integrations\IntegrationInterface;
use lindemannrock\shortlinkmanager\integrations\SeomaticIntegration;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\services\IntegrationService;
use lindemannrock\shortlinkmanager\tests\TestCase;
use nystudio107\seomatic\helpers\MetaValue;
use nystudio107\seomatic\models\MetaScript;
use nystudio107\seomatic\models\MetaScriptContainer;
use nystudio107\seomatic\Seomatic;
use nystudio107\seomatic\services\MetaBundles;
use nystudio107\seomatic\services\MetaContainers;
use nystudio107\seomatic\services\SeoElements;
use nystudio107\seomatic\variables\SeomaticVariable;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @since 5.21.1
 */
#[CoversNothing]
class SeomaticTrackingTemplateTest extends TestCase
{
    #[DataProvider('dataLayerNames')]
    public function testConfiguredDataLayerReceivesAllSelectedEvents(string $configured, string $expected, bool $include): void
    {
        $this->swapPluginComponent('seomatic', 'metaContainers', new MetaContainers());
        Seomatic::$plugin->metaContainers->createMetaContainer(MetaScriptContainer::CONTAINER_TYPE, MetaScriptContainer::CONTAINER_TYPE . 'general');
        $script = Seomatic::$plugin->script->create(['key' => 'googleTagManager', 'vars' => ['dataLayerVariableName' => ['value' => 'dataLayer']]]);
        self::assertInstanceOf(MetaScript::class, $script);
        self::assertSame($script, Seomatic::$plugin->script->get('googleTagManager'));
        $vars = $script->vars;
        $originalInclude = $script->include;
        $environmentName = 'SHORTLINK_TEST_DATA_LAYER';
        $environmentExisted = array_key_exists($environmentName, $_SERVER);
        $environmentValue = $_SERVER[$environmentName] ?? null;
        $_SERVER[$environmentName] = ' envLinkEvents ';
        try {
            $script->vars['dataLayerVariableName']['value'] = $configured;
            $script->include = $include;
            $automatic = $this->executeTracking([
                'dataLayerName' => $expected,
                'search' => '?src=qr',
            ]);
            self::assertSame(['already_queued', 'short_links_qr_scan', 'short_links_redirect'], array_column($automatic['events'], 'event'));
            self::assertSame([2100], $automatic['navigationTimes']);
            $this->assertNavigationTiming($automatic, true);
            if ($expected !== 'dataLayer') {
                self::assertSame([['event' => 'default_queue_untouched']], $automatic['defaultEvents']);
            }
        } finally {
            $script->vars = $vars;
            $script->include = $originalInclude;
            if ($environmentExisted) {
                $_SERVER[$environmentName] = $environmentValue;
            } else {
                unset($_SERVER[$environmentName]);
            }
        }
    }

    public static function dataLayerNames(): iterable
    {
        yield 'default' => ['dataLayer', 'dataLayer', true];
        yield 'custom with whitespace' => [' linkEvents ', 'linkEvents', true];
        yield 'environment value with whitespace' => ['$SHORTLINK_TEST_DATA_LAYER', 'envLinkEvents', true];
        yield 'valid unicode identifier' => ['événements', 'événements', true];
        yield 'disabled GTM retains default' => ['linkEvents', 'dataLayer', false];
        yield 'empty setting retains default' => ['', 'dataLayer', true];
    }

    public function testPreparedSiteMetadataSelectsItsOwnDataLayerAndPreservesRuntimeOverrides(): void
    {
        $sites = array_slice(Craft::$app->getSites()->getAllSites(), 0, 2);
        self::assertCount(2, $sites);
        $this->swapPluginComponent('seomatic', 'metaContainers', new MetaContainers());
        $this->swapPluginComponent('seomatic', 'metaBundles', new MetaBundles());
        $this->swapPluginComponent('seomatic', 'seoElements', new SeoElements());
        $cache = Craft::$app->getCache();
        $matchedElement = Seomatic::$matchedElement;
        $seomaticVariable = Seomatic::$seomaticVariable;
        $loading = Seomatic::$loadingMetaContainers;
        $language = Seomatic::$language;
        $metaValueState = [MetaValue::$templateObjectVars, MetaValue::$templatePreviewVars, MetaValue::$view];
        $bundleIds = (new Query())->select('id')->from('{{%seomatic_metabundles}}')->column();
        $scripts = [];
        Seomatic::$seomaticVariable = new SeomaticVariable();
        Craft::$app->set('cache', new \yii\caching\DummyCache());
        try {
            foreach ($sites as $index => $site) {
                $bundle = Seomatic::$plugin->metaBundles->getGlobalMetaBundle($site->id);
                $script = $bundle->metaContainers[MetaScriptContainer::CONTAINER_TYPE . 'general']->data['googleTagManager'];
                self::assertInstanceOf(MetaScript::class, $script);
                $scripts[] = [$script, $script->vars, $script->include, $script->environment];
                $script->vars['dataLayerVariableName']['value'] = 'siteEvents' . $index;
                $script->include = true;
                $script->environment = [];
            }
            $this->withSettings(['enableAnalytics' => true, 'enabledIntegrations' => ['seomatic']], function() use ($sites): void {
                foreach ($sites as $index => $site) {
                    $link = $this->seedShortLink(['siteId' => $site->id]);
                    $integration = new SeomaticIntegration();
                    self::assertTrue($integration->prepareMetadataForShortLink($link));
                    $script = Seomatic::$plugin->script->get('googleTagManager');
                    self::assertInstanceOf(MetaScript::class, $script);
                    self::assertSame('siteEvents' . $index, $script->vars['dataLayerVariableName']['value']);
                    $result = $this->executeTracking(['dataLayerName' => 'siteEvents' . $index, 'search' => '?src=qr&debug=1'], [], $link);
                    self::assertSame(['already_queued', 'short_links_qr_scan'], array_column($result['events'], 'event'));
                    self::assertSame([['event' => 'default_queue_untouched']], $result['defaultEvents']);
                    // SEOmatic allows template-time script variables; rendering must not reload the bundle.
                    $script->vars['dataLayerVariableName']['value'] = 'runtimeEvents' . $index;
                    $override = $this->executeTracking(['dataLayerName' => 'runtimeEvents' . $index, 'search' => '?src=qr&debug=1'], [], $link);
                    self::assertSame(['already_queued', 'short_links_qr_scan'], array_column($override['events'], 'event'));
                }
            });
        } finally {
            foreach ($scripts as [$script, $vars, $include, $environment]) {
                $script->vars = $vars;
                $script->include = $include;
                $script->environment = $environment;
            }
            $createdIds = array_diff((new Query())->select('id')->from('{{%seomatic_metabundles}}')->column(), $bundleIds);
            if ($createdIds !== []) {
                Craft::$app->getDb()->createCommand()->delete('{{%seomatic_metabundles}}', ['id' => array_values($createdIds)])->execute();
            }
            Craft::$app->set('cache', $cache);
            Seomatic::$matchedElement = $matchedElement;
            Seomatic::$seomaticVariable = $seomaticVariable;
            Seomatic::$loadingMetaContainers = $loading;
            Seomatic::$language = $language;
            [MetaValue::$templateObjectVars, MetaValue::$templatePreviewVars, MetaValue::$view] = $metaValueState;
        }
    }

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

    private function executeTracking(array $input = [], array $settings = [], ?ShortLink $link = null): array
    {
        return $this->withSettings(array_merge([
            'enableAnalytics' => true,
            'enabledIntegrations' => ['seomatic'],
            'seomaticEventPrefix' => (new Settings())->seomaticEventPrefix,
            'seomaticTrackingEvents' => ['redirect', 'qr_scan'],
        ], $settings), function() use ($input, $link): array {
            $link ??= new ShortLink(['code' => 'campaign', 'title' => "Campaign 'quoted' & </script> mobile"]);
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
