<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Stubs;

use lindemannrock\shortlinkmanager\services\DeviceDetectionService;

/**
 * Stub for `DeviceDetectionService` used by the analytics tracking suite.
 *
 * The real device-detection chain bottoms out in
 * `lindemannrock\base\device\DeviceDetection::detectLanguage()`, which calls
 * `Craft::$app->getRequest()->getQueryParam()` — a method present on
 * `craft\web\Request` but not on `craft\console\Request`. The integration
 * bootstrap initialises Craft as a console application, so any test that
 * routes through `AnalyticsTrackingService::trackClick()` would otherwise
 * blow up with `UnknownMethodException` before reaching the assertion under
 * test.
 *
 * Returning a fixed shape keeps the click-tracking tests focused on the
 * insert payload (IP hash, source metadata, salt behaviour) — device
 * detection itself is unit-test territory for the base plugin, not this
 * suite.
 *
 * @since 5.19.0
 */
final class StubDeviceDetectionService extends DeviceDetectionService
{
    /** @return array<string, mixed> */
    public function detectDevice(?string $userAgent = null): array
    {
        return [
            'deviceType' => 'desktop',
            'deviceBrand' => null,
            'deviceModel' => null,
            'browser' => 'Test Browser',
            'browserVersion' => '0.0',
            'browserEngine' => null,
            'osName' => 'TestOS',
            'osVersion' => '0',
            'clientType' => 'browser',
            'isRobot' => false,
            'isMobileApp' => false,
            'botName' => null,
            'language' => 'en',
        ];
    }
}
