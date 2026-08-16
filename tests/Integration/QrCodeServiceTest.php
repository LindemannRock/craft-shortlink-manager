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
use Craft;
use craft\cachecascade\CascadeCache;
use craft\elements\Asset;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\shortlinkmanager\services\QrCodeService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use yii\caching\CacheInterface;

require_once dirname(__DIR__) . '/Fixtures/CascadeCache.php';

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

    public function testInvalidColorOptionsFallBackToDefaults(): void
    {
        $qrCode = $this->generateWithoutCache([
            'format' => 'svg',
            'color' => ['not-a-string'],
            'bg' => 'not-a-hex-color',
            'eyeColor' => '12345g',
        ]);

        $this->assertStringContainsString('<svg', $qrCode);
        $this->assertStringContainsString('</svg>', $qrCode);
    }

    public function testValidHashPrefixedColorOptionsStillGenerate(): void
    {
        $qrCode = $this->generateWithoutCache([
            'format' => 'svg',
            'color' => '#1A73E8',
            'bg' => '#FFFFFF',
            'eyeColor' => '#111111',
        ]);

        $this->assertStringContainsString('<svg', $qrCode);
        $this->assertStringContainsString('</svg>', $qrCode);
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

    public function testLogoOverlayCleanupIsFinallyGuarded(): void
    {
        $source = (string) file_get_contents((string) (new \ReflectionClass(QrCodeService::class))->getFileName());

        $this->assertStringContainsString('finally {', $source);
        $this->assertStringContainsString('while (ob_get_level() > $bufferLevel)', $source);
        $this->assertStringContainsString('if (is_string($logoPath) && is_file($logoPath))', $source);
        $this->assertStringContainsString('@unlink($logoPath);', $source);
        $this->assertStringNotContainsString("\n            unlink(\$logoPath);", $source);
    }

    public function testQrCacheIdentityPreservesEveryExistingResultAffectingInput(): void
    {
        $service = new QrCodeService();
        $method = new \ReflectionMethod($service, '_getCacheKey');
        $baseline = [
            'https://example.com/site/shortlink',
            256,
            '010203',
            'FDFCFB',
            'png',
            4,
            'square',
            'square',
            'AABBCC',
            '42',
            20,
        ];
        $baselineKey = $method->invokeArgs($service, $baseline);
        self::assertSame(
            PluginHelper::getCacheKeyPrefix(ShortLinkManager::$plugin->id, 'qr') . md5(implode(':', $baseline)),
            $baselineKey,
        );

        $alternatives = [
            'https://other.example.com/site/shortlink',
            257,
            '111111',
            'EEEEEE',
            'svg',
            5,
            'dots',
            'rounded',
            'DDEEFF',
            '43',
            21,
        ];
        foreach ($alternatives as $index => $alternative) {
            $changed = $baseline;
            $changed[$index] = $alternative;
            self::assertNotSame($baselineKey, $method->invokeArgs($service, $changed));
        }
    }

    public function testQrBinaryCacheRoundTripUsesConfiguredFiniteTtlWithoutRendering(): void
    {
        $originalCache = Craft::$app->getCache();
        self::assertInstanceOf(CacheInterface::class, $originalCache);
        $cache = new CascadeCache();
        Craft::$app->set('cache', $cache);

        try {
            $this->withSettings([
                'cacheStorageMethod' => 'craft',
                'enableQrCodeCache' => true,
                'qrCodeCacheDuration' => 83,
                'defaultQrSize' => 256,
                'defaultQrColor' => '#000000',
                'defaultQrBgColor' => '#FFFFFF',
                'defaultQrFormat' => 'png',
                'defaultQrMargin' => 4,
                'qrModuleStyle' => 'square',
                'qrEyeStyle' => 'square',
                'qrEyeColor' => null,
                'qrLogoSize' => 20,
            ], function() use ($cache): void {
                $url = 'https://example.com/cache-without-rendering';
                $service = ShortLinkManager::$plugin->qrCode;
                $keyMethod = new \ReflectionMethod($service, '_getCacheKey');
                $identity = $keyMethod->invoke(
                    $service,
                    $url,
                    256,
                    '000000',
                    'FFFFFF',
                    'png',
                    4,
                    'square',
                    'square',
                    null,
                    null,
                    20,
                );
                $binary = "\x89PNG\r\n\x1a\n\x00cached";
                self::assertTrue(ShortLinkManager::$plugin->cacheStorage->writeQrCode($identity, $binary, 83));
                self::assertSame($binary, $service->generateQrCode($url));
                self::assertContains(83, $cache->setDurations);
            });
        } finally {
            Craft::$app->set('cache', $originalCache);
        }
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
        $assets = Asset::find()
            ->kind('image')
            ->all();

        foreach ($assets as $asset) {
            if (!$asset instanceof Asset) {
                continue;
            }

            $extension = strtolower((string)pathinfo($asset->filename, PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                continue;
            }

            $path = null;
            try {
                $path = $asset->getCopyOfFile();
                if (!is_string($path) || !is_file($path)) {
                    continue;
                }

                $imageInfo = getimagesize($path);
                if (
                    is_array($imageInfo) &&
                    in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true) &&
                    $this->hasVisibleNonWhitePixel($path)
                ) {
                    return (int)$asset->id;
                }
            } catch (\Throwable) {
                continue;
            } finally {
                if (is_string($path) && is_file($path)) {
                    @unlink($path);
                }
            }
        }

        return null;
    }

    private function hasVisibleNonWhitePixel(string $path): bool
    {
        $imageData = file_get_contents($path);
        if ($imageData === false) {
            return false;
        }

        $image = imagecreatefromstring($imageData);
        if (!$image instanceof \GdImage) {
            return false;
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);

            for ($x = 0; $x < $width; $x += max(1, (int)floor($width / 10))) {
                for ($y = 0; $y < $height; $y += max(1, (int)floor($height / 10))) {
                    $rgba = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                    if ($rgba['alpha'] < 127 && ($rgba['red'] < 250 || $rgba['green'] < 250 || $rgba['blue'] < 250)) {
                        return true;
                    }
                }
            }
        } finally {
            imagedestroy($image);
        }

        return false;
    }
}
