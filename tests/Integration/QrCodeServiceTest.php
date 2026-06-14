<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use craft\elements\Asset;
use lindemannrock\shortlinkmanager\services\QrCodeService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.19.0
 */
#[CoversClass(QrCodeService::class)]
class QrCodeServiceTest extends TestCase
{
    public function testGeneratesStyledSvgQrCode(): void
    {
        $qrCode = $this->generateWithoutCache([
            'format' => 'svg',
            'size' => 180,
            'margin' => 2,
            'color' => '1A73E8',
            'bg' => 'FFFFFF',
            'eyeColor' => '111111',
            'moduleStyle' => 'dots',
            'eyeStyle' => 'rounded',
        ]);

        $this->assertStringContainsString('<svg', $qrCode);
        $this->assertStringContainsString('</svg>', $qrCode);
    }

    public function testGeneratesSvgDataUrl(): void
    {
        $dataUrl = $this->generateDataUrlWithoutCache([
            'format' => 'svg',
            'size' => 160,
        ]);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUrl);

        $encoded = substr($dataUrl, strlen('data:image/svg+xml;base64,'));
        $decoded = base64_decode($encoded, true);

        $this->assertIsString($decoded);
        $this->assertStringContainsString('<svg', $decoded);
    }

    public function testClampsSvgQrCodeSizeToSettingsBounds(): void
    {
        $tooSmall = $this->generateWithoutCache([
            'format' => 'svg',
            'size' => 50,
        ]);
        $tooLarge = $this->generateWithoutCache([
            'format' => 'svg',
            'size' => 2000,
        ]);

        $this->assertMatchesRegularExpression('/<svg[^>]+width="100"[^>]+height="100"/', $tooSmall);
        $this->assertMatchesRegularExpression('/<svg[^>]+width="1000"[^>]+height="1000"/', $tooLarge);
    }

    public function testGeneratesPngQrCodeWhenImagickIsAvailable(): void
    {
        if (!class_exists(\Imagick::class) || !class_exists(ImagickImageBackEnd::class)) {
            $this->markTestSkipped('Imagick is not available.');
        }

        $qrCode = $this->generateWithoutCache([
            'format' => 'png',
            'size' => 160,
            'margin' => 2,
        ]);

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $qrCode);
    }

    public function testGeneratesPngQrCodeWithLogoOverlayWhenImageAssetIsAvailable(): void
    {
        if (!class_exists(\Imagick::class) || !class_exists(ImagickImageBackEnd::class)) {
            $this->markTestSkipped('Imagick is not available.');
        }
        if (!function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD image functions are not available.');
        }

        $logoId = $this->findImageAssetId();
        if ($logoId === null) {
            $this->markTestSkipped('No image asset is available for logo overlay smoke testing.');
        }

        $options = [
            'format' => 'png',
            'size' => 220,
            'margin' => 2,
            'logoSize' => 18,
        ];

        $withoutLogo = $this->generateWithoutCache($options);
        $withLogo = $this->generateWithoutCache($options + ['logo' => $logoId]);

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $withLogo);
        $this->assertNotSame($withoutLogo, $withLogo, 'Logo overlay should modify the generated PNG bytes.');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function generateWithoutCache(array $options): string
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $originalCacheSetting = $settings->enableQrCodeCache;
        $settings->enableQrCodeCache = false;

        try {
            return ShortLinkManager::$plugin->qrCode->generateQrCode('https://example.com/qr-test', $options);
        } finally {
            $settings->enableQrCodeCache = $originalCacheSetting;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function generateDataUrlWithoutCache(array $options): string
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $originalCacheSetting = $settings->enableQrCodeCache;
        $settings->enableQrCodeCache = false;

        try {
            return ShortLinkManager::$plugin->qrCode->generateQrCodeDataUrl('https://example.com/qr-test-data-url', $options);
        } finally {
            $settings->enableQrCodeCache = $originalCacheSetting;
        }
    }

    private function findImageAssetId(): ?int
    {
        $asset = Asset::find()
            ->kind('image')
            ->one();

        return $asset ? (int) $asset->id : null;
    }
}
