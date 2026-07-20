<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\base\helpers\SettingsPostHelper;
use lindemannrock\shortlinkmanager\controllers\SettingsController;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.20.0
 */
#[CoversClass(SettingsController::class)]
final class SettingsControllerSectionScopeTest extends TestCase
{
    public function testSettingsSectionsMatchRenderedFormScopes(): void
    {
        $controller = new SettingsController('settings', ShortLinkManager::$plugin);
        $method = new \ReflectionMethod($controller, '_validationAttributesForSection');

        $expected = [
            'general' => [
                'pluginName',
                'enabledSites',
                'usePrefix',
                'slugPrefix',
                'qrPrefix',
                'shortlinkBaseUrl',
                'codeLength',
                'redirectTemplate',
                'expiredTemplate',
                'qrTemplate',
                'expiredMessage',
                'logLevel',
            ],
            'behavior' => [
                'notFoundRedirectUrl',
                'defaultHttpCode',
                'passQueryParams',
                'directRedirect',
            ],
            'qr-code' => [
                'defaultQrSize',
                'defaultQrFormat',
                'defaultQrColor',
                'defaultQrBgColor',
                'defaultQrMargin',
                'qrModuleStyle',
                'qrEyeStyle',
                'qrEyeColor',
                'enableQrLogo',
                'qrLogoVolumeUid',
                'defaultQrLogoId',
                'qrLogoSize',
                'defaultQrErrorCorrection',
                'enableQrDownload',
                'qrDownloadFilename',
            ],
            'analytics' => [
                'enableAnalytics',
                'enableGeoDetection',
                'geoProvider',
                'geoApiKey',
                'anonymizeIpAddress',
                'analyticsRetention',
            ],
            'integrations' => [
                'enabledIntegrations',
                'seomaticTrackingEvents',
                'seomaticEventPrefix',
                'redirectManagerEvents',
            ],
            'interface' => [
                'itemsPerPage',
                'timeFormat',
                'monthFormat',
                'dateOrder',
                'dateSeparator',
                'showSeconds',
                'defaultDateRange',
                'exportsCsv',
                'exportsJson',
                'exportsExcel',
            ],
            'cache' => [
                'cacheStorageMethod',
                'enableQrCodeCache',
                'qrCodeCacheDuration',
                'cacheDeviceDetection',
                'deviceDetectionCacheDuration',
            ],
        ];

        foreach ($expected as $section => $attributes) {
            self::assertSame($attributes, $method->invoke($controller, $section), "Unexpected {$section} settings scope.");
        }
    }

    public function testStaleDefaultQrLogoIdNormalizesToNull(): void
    {
        $controller = new SettingsController('settings', ShortLinkManager::$plugin);
        $method = new \ReflectionMethod($controller, 'normalizeDefaultQrLogoId');
        $settings = new Settings();

        self::assertNull($method->invoke($controller, 54042, $settings));
    }

    public function testEmptyDefaultQrLogoElementSelectPayloadDoesNotAddIntegerError(): void
    {
        $controller = new SettingsController('settings', ShortLinkManager::$plugin);
        $method = new \ReflectionMethod($controller, 'normalizeDefaultQrLogoId');
        $settings = new Settings();

        $result = SettingsPostHelper::apply(
            model: $settings,
            postedValues: ['defaultQrLogoId' => ['']],
            allowedAttributes: ['defaultQrLogoId'],
            adapters: [
                'defaultQrLogoId' => fn(mixed $value): ?int => $method->invoke($controller, $value, $settings),
            ],
        );

        self::assertFalse($result->hasErrors);
        self::assertSame([], $settings->getErrors('defaultQrLogoId'));
        self::assertNull($settings->defaultQrLogoId);
    }

    public function testQrSettingsTemplateDoesNotPassNullLogoElement(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/qr-code.twig');
        self::assertIsString($source);

        self::assertStringContainsString('{% set selectedLogo = settings.defaultQrLogoId ? craft.assets.id(settings.defaultQrLogoId).one() : null %}', $source);
        self::assertStringContainsString('elements: selectedLogo ? [selectedLogo] : [],', $source);
        self::assertStringNotContainsString('elements: settings.defaultQrLogoId ? [craft.assets.id(settings.defaultQrLogoId).one()] : [],', $source);
    }

    public function testSetupCompleteInfoBoxUsesConfiguredPluginName(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/templates/setup.twig');
        self::assertIsString($source);

        self::assertStringContainsString("{% set title = 'Set up {pluginName}'|t('shortlink-manager', {", $source);
        self::assertStringContainsString('pluginName: shortlinkHelper.fullName', $source);
        self::assertStringNotContainsString('Set up ShortLink Manager', $source);
        self::assertStringContainsString('{% set shortlinkFullNameHtml = shortlinkHelper.fullName|e %}', $source);
        self::assertStringContainsString('shortlinkFullNameHtml: shortlinkFullNameHtml,', $source);
        self::assertStringContainsString("'{pluginName} is ready to create public short links and QR landing pages.'|t('shortlink-manager', {", $source);
        self::assertStringContainsString('pluginName: shortlinkFullNameHtml', $source);
        self::assertStringNotContainsString("'ShortLink Manager is ready to create public short links and QR landing pages.'|t('shortlink-manager')", $source);
    }

    public function testDirectRedirectCacheWarningAllowsCodeMarkup(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/behavior.twig');
        self::assertIsString($source);

        self::assertSame(2, substr_count($source, 'directRedirectCacheFollowup,' . PHP_EOL . '            })|raw'));
        self::assertStringContainsString('<code>/abc123</code>', $source);
        self::assertStringContainsString('routeExamples: directRedirectRouteExamplesHtml', $source);
    }

    public function testHttpStatusTipLinkLabelsAreEscapedForJavaScript(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/behavior.twig');
        self::assertIsString($source);

        self::assertSame(4, substr_count($source, '"Learn more"|t(\'shortlink-manager\')|e(\'js\')'));
        self::assertStringNotContainsString('{{ "Learn more"|t(\'shortlink-manager\') }}</a>\';', $source);
    }

    public function testRecentClicksDestinationTitleUsesAttributeEscaping(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $source = file_get_contents($pluginRoot . '/src/web/assets/analytics/src/analytics.js');
        $dist = file_get_contents($pluginRoot . '/src/web/assets/analytics/dist/analytics.js');
        self::assertIsString($source);
        self::assertIsString($dist);

        self::assertStringContainsString('function escAttr(str)', $source);
        self::assertStringContainsString('title="\' + escAttr(dest)', $source);
        self::assertStringNotContainsString('title="\' + esc(dest)', $source);
        self::assertStringContainsString('&quot;', $dist);
        self::assertStringContainsString('&#039;', $dist);
    }

    public function testInstructionPlaceholdersEscapeConfiguredPluginName(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $files = [
            '/src/templates/settings/general.twig',
            '/src/templates/settings/behavior.twig',
            '/src/templates/settings/analytics.twig',
            '/src/templates/_components/fields/ShortLinkField/settings.twig',
            '/src/templates/shortlinks/edit.twig',
            '/src/templates/shortlinks/_partials/fields.twig',
        ];

        foreach ($files as $file) {
            $source = file_get_contents($pluginRoot . $file);
            self::assertIsString($source);
            self::assertDoesNotMatchRegularExpression('/instructions:.*shortlinkHelper\\.(?:lowerDisplayName|pluralLowerDisplayName|fullName|displayName)/', $source, $file);
        }
    }

    public function testRawSettingsInfoBoxesEscapeConfiguredPluginName(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $general = file_get_contents($pluginRoot . '/src/templates/settings/general.twig');
        $integrations = file_get_contents($pluginRoot . '/src/templates/settings/integrations.twig');
        self::assertIsString($general);
        self::assertIsString($integrations);

        self::assertStringContainsString('{% set shortlinkFullNameHtml = shortlinkHelper.fullName|e %}', $general);
        self::assertStringContainsString('{% set shortlinkDisplayNameHtml = shortlinkHelper.displayName|e %}', $general);
        self::assertStringContainsString('{% set shortlinkPluralLowerNameHtml = shortlinkHelper.pluralLowerDisplayName|e %}', $general);
        self::assertStringContainsString('shortName: shortlinkFullNameHtml', $general);
        self::assertStringContainsString('singularName: shortlinkDisplayNameHtml', $general);
        self::assertStringContainsString('pluginName: shortlinkFullNameHtml', $general);
        self::assertStringContainsString('pluginName: shortlinkPluralLowerNameHtml', $general);
        self::assertStringNotContainsString('shortName: shortlinkHelper.fullName', $general);
        self::assertStringNotContainsString('message: "URL Prefix is disabled. {singularName} URLs will be generated as root paths like <code>/abc123</code>."|t(\'shortlink-manager\', {singularName: shortlinkHelper.displayName})|raw', $general);
        self::assertStringNotContainsString('singularName: shortlinkHelper.displayName, siteHandle:', $general);
        self::assertStringNotContainsString('pluginName: shortlinkHelper.fullName', $general);
        self::assertStringNotContainsString('pluginName: shortlinkHelper.pluralLowerDisplayName', $general);

        self::assertStringContainsString('{% set shortlinkFullNameHtml = shortlinkHelper.fullName|e %}', $integrations);
        self::assertStringContainsString('{% set shortlinkPluralLowerNameHtml = shortlinkHelper.pluralLowerDisplayName|e %}', $integrations);
        self::assertStringContainsString('pluginName: shortlinkFullNameHtml', $integrations);
        self::assertStringContainsString('pluginName: shortlinkPluralLowerNameHtml', $integrations);
        self::assertStringContainsString('~ shortlinkFullNameHtml ~', $integrations);
        self::assertStringNotContainsString("|t('shortlink-manager', {pluginName: shortlinkHelper.fullName}) ~", $integrations);
        self::assertStringNotContainsString('~ shortlinkHelper.fullName ~', $integrations);
        self::assertStringNotContainsString('pluginName: shortlinkHelper.pluralLowerDisplayName', $integrations);
    }

    public function testRawInfoBoxMessagesEscapeDynamicPlaceholders(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $files = [
            '/src/templates/settings/behavior.twig' => [
                'contains' => [
                    'prefixExample|e',
                ],
                'notContains' => [
                    '{prefix: prefixExample})',
                ],
            ],
            '/src/templates/settings/cache.twig' => [
                'contains' => [
                    '{% set shortlinkCacheBasePathHtml = shortlinkHelper.cacheBasePath|e %}',
                    'path: shortlinkCacheBasePathHtml',
                ],
                'notContains' => [
                    'path: shortlinkHelper.cacheBasePath',
                ],
            ],
            '/src/templates/settings/integrations.twig' => [
                'contains' => [
                    '{% set seomaticPluginNameHtml = seomaticPluginName|e %}',
                    '{% set rmPluginNameHtml = rmPluginName|e %}',
                    "url: url('shortlink-manager/settings/behavior')|e('html_attr')",
                    'pluginName: seomaticPluginNameHtml',
                    'rmPluginName: rmPluginNameHtml',
                ],
                'notContains' => [
                    "url: url('shortlink-manager/settings/behavior')})",
                    'message: \'<strong>\' ~ "Note"|t(\'shortlink-manager\') ~ \':</strong> \' ~ "No tracking scripts are currently configured in {pluginName}. Events will be queued but not sent until you configure GTM or Google Analytics in {pluginName}."|t(\'shortlink-manager\', { pluginName: seomaticPluginName })',
                    ' ~ "{rmPluginName} shows which plugin created each redirect for better organization"|t(\'shortlink-manager\', {rmPluginName: rmPluginName}) ~ ',
                ],
            ],
            '/src/templates/utilities/index.twig' => [
                'contains' => [
                    '{% set selectedSiteLabelHtml = selectedSiteLabel|e %}',
                    '~ selectedSiteLabelHtml',
                ],
                'notContains' => [
                    '~ selectedSiteLabel,',
                ],
            ],
        ];

        foreach ($files as $file => $expectations) {
            $source = file_get_contents($pluginRoot . $file);
            self::assertIsString($source);

            foreach ($expectations['contains'] as $expected) {
                self::assertStringContainsString($expected, $source, $file);
            }

            foreach ($expectations['notContains'] as $unexpected) {
                self::assertStringNotContainsString($unexpected, $source, $file);
            }
        }
    }
}
