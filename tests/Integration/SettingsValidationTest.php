<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.21.0
 */
#[CoversClass(Settings::class)]
final class SettingsValidationTest extends TestCase
{
    public function testQrDownloadFilenameRejectsSlugToken(): void
    {
        $settings = new Settings();
        $settings->qrDownloadFilename = '{slug}-qr-{size}';

        self::assertFalse($settings->validate(['qrDownloadFilename']));
        self::assertNotEmpty($settings->getErrors('qrDownloadFilename'));
    }

    public function testQrDownloadFilenameAcceptsSupportedTokens(): void
    {
        $settings = new Settings();
        $settings->qrDownloadFilename = '{code}-qr-{size}.{format}';

        self::assertTrue($settings->validate(['qrDownloadFilename']), json_encode($settings->getErrors()));
    }
}
