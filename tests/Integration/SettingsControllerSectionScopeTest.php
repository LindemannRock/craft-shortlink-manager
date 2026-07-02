<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\shortlinkmanager\controllers\SettingsController;
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

    public function testDirectRedirectCacheWarningAllowsCodeMarkup(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/behavior.twig');
        self::assertIsString($source);

        self::assertSame(2, substr_count($source, 'directRedirectCacheFollowup,' . PHP_EOL . '            })|raw'));
        self::assertStringContainsString('<code>/abc123</code>', $source);
        self::assertStringContainsString('routeExamples: directRedirectRouteExamplesHtml', $source);
    }
}
