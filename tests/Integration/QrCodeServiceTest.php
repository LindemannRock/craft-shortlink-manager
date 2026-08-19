<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Eye\SquareEye;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Module\SquareModule;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Craft;
use craft\cachecascade\CascadeCache;
use craft\elements\Asset;
use craft\services\Images;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\helpers\QrCodeRendererHelper;
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
final class QrCodeServiceTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->temporaryFiles = [];

        parent::tearDown();
    }

    public function testGeneratesValidPngWithEffectiveImagickDriver(): void
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            $this->markTestSkipped('Imagick is not available.');
        }

        $png = $this->withEffectiveImageDriver(Images::DRIVER_IMAGICK, fn(): string => $this->generateWithoutCache([
            'format' => 'png',
            'size' => 180,
            'margin' => 2,
        ]));

        $this->assertValidPng($png, 180);
    }

    public function testGeneratesValidPngWithEffectiveGdDriver(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is not available.');
        }

        $png = $this->withEffectiveImageDriver(Images::DRIVER_GD, fn(): string => $this->generateWithoutCache([
            'format' => 'png',
            'size' => 180,
            'margin' => 2,
        ]));

        $this->assertValidPng($png, 180);
    }

    public function testGeneratesStyledPngForEverySupportedModuleAndEyeCombination(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is not available.');
        }

        $rendered = $this->withEffectiveImageDriver(Images::DRIVER_GD, function(): array {
            $outputs = [];
            foreach (['square', 'rounded', 'dots'] as $moduleStyle) {
                foreach (['square', 'rounded', 'pointed'] as $eyeStyle) {
                    $key = $moduleStyle . ':' . $eyeStyle;
                    $outputs[$key] = $this->generateWithoutCache([
                        'format' => 'png',
                        'size' => 240,
                        'margin' => 4,
                        'color' => '123456',
                        'bg' => 'F5E6D3',
                        'eyeColor' => 'AA2244',
                        'moduleStyle' => $moduleStyle,
                        'eyeStyle' => $eyeStyle,
                    ]);
                }
            }

            return $outputs;
        });

        self::assertCount(9, $rendered);
        foreach ($rendered as $png) {
            $this->assertValidPng($png, 240);
        }
        self::assertCount(9, array_unique(array_map('md5', $rendered)));
    }

    public function testGeneratesStyledSvgIndependentlyOfRasterDriver(): void
    {
        $svg = $this->withEffectiveImageDriver('unavailable', fn(): string => $this->generateWithoutCache([
            'format' => 'svg',
            'size' => 180,
            'margin' => 2,
            'color' => '1A73E8',
            'bg' => 'FFFFFF',
            'eyeColor' => '111111',
            'moduleStyle' => 'dots',
            'eyeStyle' => 'rounded',
        ]));

        $this->assertValidSvg($svg, 180);
    }

    public function testAppliesEverySupportedErrorCorrectionLevelToPngAndSvg(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is not available.');
        }

        $outputs = ['png' => [], 'svg' => []];
        foreach (['L', 'M', 'Q', 'H'] as $errorCorrection) {
            foreach (['png', 'svg'] as $format) {
                $render = function() use ($errorCorrection, $format): array {
                    $options = [
                        'format' => $format,
                        'size' => 200,
                        'margin' => 3,
                        'errorCorrection' => $errorCorrection,
                    ];

                    return [
                        $this->generateWithoutCache($options),
                        $this->explicitBaconOutput('https://example.com/qr-test', $format, $errorCorrection, 200, 3),
                    ];
                };

                [$actual, $expected] = $format === 'png'
                    ? $this->withEffectiveImageDriver(Images::DRIVER_GD, $render)
                    : $render();

                self::assertSame($expected, $actual, "{$format} must use Bacon error correction {$errorCorrection} explicitly.");
                $outputs[$format][] = md5($actual);
                $format === 'png'
                    ? $this->assertValidPng($actual, 200)
                    : $this->assertValidSvg($actual, 200);
            }
        }

        self::assertCount(4, array_unique($outputs['png']));
        self::assertCount(4, array_unique($outputs['svg']));
    }

    public function testAppliesEverySupportedErrorCorrectionLevelWithEffectiveImagickDriver(): void
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            $this->markTestSkipped('Imagick is not available.');
        }

        $this->withEffectiveImageDriver(Images::DRIVER_IMAGICK, function(): void {
            foreach (['L', 'M', 'Q', 'H'] as $errorCorrection) {
                $options = [
                    'format' => 'png',
                    'size' => 200,
                    'margin' => 3,
                    'errorCorrection' => $errorCorrection,
                ];
                $actual = $this->generateWithoutCache($options);
                $expected = $this->explicitBaconOutput('https://example.com/qr-test', 'png', $errorCorrection, 200, 3);

                self::assertSame($expected, $actual);
                $this->assertValidPng($actual, 200);
            }
        });
    }

    public function testConfiguredErrorCorrectionControlsDefaultGeneration(): void
    {
        $this->withSettings($this->renderSettings([
            'defaultQrErrorCorrection' => 'Q',
        ]), function(): void {
            $actual = ShortLinkManager::$plugin->qrCode->generateQrCode('https://example.com/configured-error-correction', ['format' => 'svg']);
            $expected = $this->explicitBaconOutput('https://example.com/configured-error-correction', 'svg', 'Q');

            self::assertSame($expected, $actual);
        });
    }

    public function testNormalizesCaseInsensitiveErrorCorrectionOptions(): void
    {
        $upper = $this->generateWithoutCache(['format' => 'svg', 'errorCorrection' => 'Q']);

        foreach (['q', ' q ', "\tQ\n"] as $errorCorrection) {
            self::assertSame($upper, $this->generateWithoutCache([
                'format' => 'svg',
                'errorCorrection' => $errorCorrection,
            ]));
        }
    }

    public function testInvalidErrorCorrectionFallsBackToEffectiveConfiguredDefault(): void
    {
        $this->withSettings($this->renderSettings([
            'defaultQrErrorCorrection' => 'H',
        ]), function(): void {
            $expected = $this->explicitBaconOutput('https://example.com/qr-test', 'svg', 'H');

            foreach (['invalid', '', [], new \stdClass()] as $errorCorrection) {
                self::assertSame($expected, ShortLinkManager::$plugin->qrCode->generateQrCode('https://example.com/qr-test', [
                    'format' => 'svg',
                    'errorCorrection' => $errorCorrection,
                ]));
            }
        });
    }

    public function testInvalidConfiguredErrorCorrectionFallsBackToMedium(): void
    {
        $this->withSettings($this->renderSettings([
            'defaultQrErrorCorrection' => 'invalid',
        ]), function(): void {
            $expected = $this->explicitBaconOutput('https://example.com/invalid-configured-error-correction', 'svg', 'M');

            self::assertSame($expected, ShortLinkManager::$plugin->qrCode->generateQrCode(
                'https://example.com/invalid-configured-error-correction',
                ['format' => 'svg', 'errorCorrection' => []],
            ));
        });
    }

    public function testPngAndSvgPreserveDimensionsMarginAndColors(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is not available.');
        }

        $options = [
            'size' => 240,
            'margin' => 6,
            'color' => '123456',
            'bg' => 'F5E6D3',
            'eyeColor' => 'AA2244',
            'moduleStyle' => 'rounded',
            'eyeStyle' => 'pointed',
        ];
        $png = $this->withEffectiveImageDriver(Images::DRIVER_GD, fn(): string => $this->generateWithoutCache(array_replace($options, ['format' => 'png'])));
        $svg = $this->generateWithoutCache(array_replace($options, ['format' => 'svg']));
        $zeroMarginSvg = $this->generateWithoutCache(array_replace($options, ['format' => 'svg', 'margin' => 0]));

        $this->assertValidPng($png, 240);
        $this->assertValidSvg($svg, 240);
        self::assertNotSame($svg, $zeroMarginSvg);
        self::assertStringContainsString('#123456', $svg);
        self::assertStringContainsString('#f5e6d3', $svg);
        self::assertStringContainsString('#aa2244', $svg);
    }

    public function testDataUrlMimeMatchesNormalizedGeneratedBytes(): void
    {
        $this->withSettings($this->renderSettings(['defaultQrFormat' => 'svg']), function(): void {
            $dataUrl = ShortLinkManager::$plugin->qrCode->generateQrCodeDataUrl(
                'https://example.com/qr-test-data-url',
                ['format' => 'invalid'],
            );
            self::assertStringStartsWith('data:image/svg+xml;base64,', $dataUrl);
            $this->assertValidSvg($this->decodeDataUrl($dataUrl));
        });

        if (!extension_loaded('gd')) {
            return;
        }

        $this->withSettings($this->renderSettings(['defaultQrFormat' => 'png']), function(): void {
            $dataUrl = $this->withEffectiveImageDriver(
                Images::DRIVER_GD,
                fn(): string => ShortLinkManager::$plugin->qrCode->generateQrCodeDataUrl(
                    'https://example.com/qr-test-data-url',
                    ['format' => 'invalid'],
                ),
            );
            self::assertStringStartsWith('data:image/png;base64,', $dataUrl);
            $this->assertValidPng($this->decodeDataUrl($dataUrl));
        });

        $highDataUrl = $this->generateDataUrlWithoutCache([
            'format' => 'svg',
            'errorCorrection' => 'H',
        ]);
        self::assertSame(
            $this->explicitBaconOutput('https://example.com/qr-test-data-url', 'svg', 'H'),
            $this->decodeDataUrl($highDataUrl),
        );
    }

    public function testLogoOverlaySupportsLocalAsset(): void
    {
        foreach (['jpeg', 'png', 'gif'] as $format) {
            $this->assertLogoOverlayForTemporaryAsset('local', $format);
        }
    }

    public function testLogoOverlaySupportsRemoteVolumeAsset(): void
    {
        $this->assertLogoOverlayForTemporaryAsset('remote', 'png');
    }

    public function testLogoOverlayPreservesRequestedErrorCorrectionGeneration(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is not available.');
        }

        $asset = new StubQrLogoAsset($this->createLogoFile('png'));
        $service = new StubLogoQrCodeService();
        $service->logoAsset = $asset;
        $options = [
            'format' => 'png',
            'size' => 220,
            'margin' => 4,
            'errorCorrection' => 'H',
            'logoSize' => 18,
        ];

        [$base, $branded] = $this->withEffectiveImageDriver(Images::DRIVER_GD, fn(): array => [
            $this->generateWithServiceWithoutCache($service, $options),
            $this->generateWithServiceWithoutCache($service, $options + ['logo' => '42']),
        ]);

        self::assertSame($this->withEffectiveImageDriver(
            Images::DRIVER_GD,
            fn(): string => $this->explicitBaconOutput('https://example.com/qr-test', 'png', 'H', 220, 4),
        ), $base);
        $this->assertValidPng($branded, 220);
        self::assertNotSame($base, $branded);
        self::assertCount(1, $asset->createdCopies);
        self::assertFileDoesNotExist($asset->createdCopies[0]);
    }

    public function testMissingLogoReturnsValidBasePng(): void
    {
        $this->assertLogoFailureReturnsBasePng(null);
    }

    public function testCorruptLogoReturnsValidBasePng(): void
    {
        $this->assertLogoFailureReturnsBasePng(new StubQrLogoAsset($this->temporaryFile('corrupt-logo', 'not an image')));
    }

    public function testInaccessibleLogoReturnsValidBasePng(): void
    {
        $asset = new StubQrLogoAsset('');
        $asset->throwOnCopy = true;
        $this->assertLogoFailureReturnsBasePng($asset);
    }

    public function testUnsupportedLogoReturnsValidBasePng(): void
    {
        if (!function_exists('imagebmp')) {
            $this->markTestSkipped('BMP output is not available for the unsupported-format fixture.');
        }

        $this->assertLogoFailureReturnsBasePng(new StubQrLogoAsset($this->createLogoFile('bmp')));
    }

    public function testRendererAndLogoCleanupRestoresResourcesBuffersAndTemporaryCopies(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is not available.');
        }

        $asset = new StubQrLogoAsset($this->createLogoFile('png'));
        $service = new StubLogoQrCodeService();
        $service->logoAsset = $asset;
        $startLevel = ob_get_level();

        for ($generation = 0; $generation < 3; $generation++) {
            $png = $this->withEffectiveImageDriver(Images::DRIVER_GD, fn(): string => $this->generateWithServiceWithoutCache($service, ['format' => 'png', 'logo' => '42']));
            $this->assertValidPng($png);
            self::assertSame($startLevel, ob_get_level());
        }

        self::assertCount(3, $asset->createdCopies);
        self::assertSame($startLevel, ob_get_level());
        foreach ($asset->createdCopies as $copy) {
            self::assertFileDoesNotExist($copy);
        }
    }

    public function testLogoEncodingFailureRestoresBuffersAndTemporaryCopies(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is not available.');
        }

        $asset = new StubQrLogoAsset($this->createLogoFile('png'));
        $service = new FailingLogoEncodingQrCodeService();
        $service->logoAsset = $asset;
        $options = ['format' => 'png', 'size' => 220, 'logoSize' => 18];
        $base = $this->withEffectiveImageDriver(Images::DRIVER_GD, fn(): string => $this->generateWithServiceWithoutCache($service, $options));
        $startLevel = ob_get_level();

        $withLogo = $this->withEffectiveImageDriver(Images::DRIVER_GD, fn(): string => $this->generateWithServiceWithoutCache($service, $options + ['logo' => '42']));

        $this->assertValidPng($withLogo, 220);
        self::assertSame($base, $withLogo);
        self::assertSame($startLevel, ob_get_level());
        self::assertCount(1, $asset->createdCopies);
        self::assertFileDoesNotExist($asset->createdCopies[0]);
    }

    public function testFailedGenerationIsNotCached(): void
    {
        $this->assertRejectedGenerationIsNotCached(new ThrowingQrCodeService());
    }

    public function testInvalidGeneratedOutputIsNotCached(): void
    {
        foreach ([
            ['format' => 'png', 'output' => ''],
            ['format' => 'png', 'output' => "\x89PNG\r\n\x1a\npartial"],
            ['format' => 'png', 'output' => '<svg></svg>'],
            ['format' => 'svg', 'output' => '<svg>partial'],
            ['format' => 'svg', 'output' => "\x89PNG\r\n\x1a\nwrong-format"],
        ] as $case) {
            $service = new InvalidOutputQrCodeService();
            $service->output = $case['output'];
            $this->assertRejectedGenerationIsNotCached($service, ['defaultQrFormat' => $case['format']]);
        }
    }

    public function testInvalidCacheHitIsRegenerated(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is not available.');
        }

        $this->withCraftCache(function(CascadeCache $cache): void {
            $this->withSettings($this->cacheSettings(), function() use ($cache): void {
                $url = 'https://example.com/invalid-cache-hit';
                $service = ShortLinkManager::$plugin->qrCode;
                $identity = $this->cacheIdentity($service, $url);
                self::assertTrue(ShortLinkManager::$plugin->cacheStorage->writeQrCode($identity, "\x89PNG\r\n\x1a\npartial", 83));

                $png = $this->withEffectiveImageDriver(Images::DRIVER_GD, fn(): string => $service->generateQrCode($url));

                $this->assertValidPng($png, 256);
                self::assertGreaterThanOrEqual(2, count($cache->setDurations));
                $cached = ShortLinkManager::$plugin->cacheStorage->readQrCode($identity, 83);
                self::assertTrue($cached->isHit());
                self::assertSame($png, $cached->value);
            });
        });
    }

    public function testCacheEnabledAndDisabledPreserveGenerationContract(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is not available.');
        }

        $this->withCraftCache(function(CascadeCache $cache): void {
            $this->withSettings($this->cacheSettings(), function() use ($cache): void {
                $enabled = $this->withEffectiveImageDriver(Images::DRIVER_GD, fn(): string => ShortLinkManager::$plugin->qrCode->generateQrCode('https://example.com/cache-enabled'));
                $writes = count($cache->setDurations);
                self::assertGreaterThan(0, $writes);

                $disabled = $this->withSettings(['enableQrCodeCache' => false], fn(): string => $this->withEffectiveImageDriver(Images::DRIVER_GD, fn(): string => ShortLinkManager::$plugin->qrCode->generateQrCode('https://example.com/cache-disabled')));
                self::assertSame($writes, count($cache->setDurations));
                $this->assertValidPng($enabled, 256);
                $this->assertValidPng($disabled, 256);
            });
        });
    }

    public function testEquivalentRequestStylesShareCacheIdentity(): void
    {
        $this->withCraftCache(function(CascadeCache $cache): void {
            $this->withSettings($this->cacheSettings(['defaultQrFormat' => 'svg']), function() use ($cache): void {
                $service = ShortLinkManager::$plugin->qrCode;
                $url = 'https://example.com/equivalent-styles';
                $first = $service->generateQrCode($url, ['format' => 'invalid', 'moduleStyle' => 'invalid', 'eyeStyle' => 'invalid']);
                $second = $service->generateQrCode($url, ['format' => 'svg']);

                self::assertSame($first, $second);
                self::assertCount(1, $cache->setDurations);
            });
        });
    }

    public function testEquivalentErrorCorrectionInputsShareCacheIdentity(): void
    {
        $this->withCraftCache(function(CascadeCache $cache): void {
            $this->withSettings($this->cacheSettings(['defaultQrFormat' => 'svg']), function() use ($cache): void {
                $service = ShortLinkManager::$plugin->qrCode;
                $url = 'https://example.com/equivalent-error-correction';
                $first = $service->generateQrCode($url, ['errorCorrection' => 'm']);
                $second = $service->generateQrCode($url, ['errorCorrection' => ' M ']);

                self::assertSame($first, $second);
                self::assertCount(1, $cache->setDurations);
            });
        });
    }

    public function testInvalidErrorCorrectionSharesConfiguredDefaultCacheIdentity(): void
    {
        $this->withCraftCache(function(CascadeCache $cache): void {
            $this->withSettings($this->cacheSettings([
                'defaultQrFormat' => 'svg',
                'defaultQrErrorCorrection' => 'Q',
            ]), function() use ($cache): void {
                $service = ShortLinkManager::$plugin->qrCode;
                $url = 'https://example.com/invalid-error-correction-cache';
                $fallback = $service->generateQrCode($url, ['errorCorrection' => []]);
                $explicit = $service->generateQrCode($url, ['errorCorrection' => 'Q']);

                self::assertSame($fallback, $explicit);
                self::assertCount(1, $cache->setDurations);
            });
        });
    }

    public function testInvalidConfiguredErrorCorrectionSharesMediumCacheIdentity(): void
    {
        $this->withCraftCache(function(CascadeCache $cache): void {
            $this->withSettings($this->cacheSettings([
                'defaultQrFormat' => 'svg',
                'defaultQrErrorCorrection' => 'invalid',
            ]), function() use ($cache): void {
                $service = ShortLinkManager::$plugin->qrCode;
                $url = 'https://example.com/invalid-configured-error-correction-cache';
                $fallback = $service->generateQrCode($url);
                $explicit = $service->generateQrCode($url, ['errorCorrection' => 'M']);

                self::assertSame($fallback, $explicit);
                self::assertCount(1, $cache->setDurations);
            });
        });
    }

    public function testErrorCorrectionChangeDoesNotReuseCachedOutput(): void
    {
        $this->withCraftCache(function(CascadeCache $cache): void {
            $this->withSettings($this->cacheSettings(['defaultQrFormat' => 'svg']), function() use ($cache): void {
                $service = ShortLinkManager::$plugin->qrCode;
                $url = 'https://example.com/error-correction-cache-change';
                $low = $service->generateQrCode($url, ['errorCorrection' => 'L']);
                $high = $service->generateQrCode($url, ['errorCorrection' => 'H']);

                self::assertNotSame($low, $high);
                self::assertCount(2, $cache->setDurations);
                self::assertSame($low, $service->generateQrCode($url, ['errorCorrection' => 'l']));
                self::assertCount(2, $cache->setDurations);
            });
        });
    }

    public function testConfigOverridesPreserveEffectiveRenderingOptions(): void
    {
        $this->withSettings([
            'enableQrCodeCache' => false,
            'defaultQrFormat' => 'svg',
            'defaultQrSize' => 210,
            'defaultQrMargin' => 3,
            'defaultQrColor' => '#123456',
            'defaultQrBgColor' => '#F5E6D3',
            'qrModuleStyle' => 'rounded',
            'qrEyeStyle' => 'pointed',
            'qrEyeColor' => '#AA2244',
        ], function(): void {
            $svg = ShortLinkManager::$plugin->qrCode->generateQrCode('https://example.com/config-overrides');
            $this->assertValidSvg($svg, 210);
            self::assertStringContainsString('#123456', $svg);
            self::assertStringContainsString('#f5e6d3', $svg);
            self::assertStringContainsString('#aa2244', $svg);
        });
    }

    public function testQrCacheIdentityPreservesEveryExistingResultAffectingInput(): void
    {
        $service = new QrCodeService();
        $method = new \ReflectionMethod($service, '_getCacheKey');
        $baseline = ['https://example.com/site/shortlink', 256, '010203', 'FDFCFB', 'png', 4, 'M', 'square', 'square', 'AABBCC', '42', 20];
        $baselineKey = $method->invokeArgs($service, $baseline);
        self::assertSame(PluginHelper::getCacheKeyPrefix(ShortLinkManager::$plugin->id, 'qr') . md5(implode(':', $baseline)), $baselineKey);
        $legacyIdentity = $baseline;
        array_splice($legacyIdentity, 6, 1);
        self::assertNotSame(
            PluginHelper::getCacheKeyPrefix(ShortLinkManager::$plugin->id, 'qr') . md5(implode(':', $legacyIdentity)),
            $baselineKey,
        );

        $alternatives = ['https://other.example.com/site/shortlink', 257, '111111', 'EEEEEE', 'svg', 5, 'H', 'dots', 'rounded', 'DDEEFF', '43', 21];
        foreach ($alternatives as $index => $alternative) {
            $changed = $baseline;
            $changed[$index] = $alternative;
            self::assertNotSame($baselineKey, $method->invokeArgs($service, $changed));
        }
    }

    public function testShortLinkHelpersForwardErrorCorrectionOptions(): void
    {
        $link = $this->seedShortLink();
        $link->qrCodeEnabled = true;
        $link->qrCodeFormat = 'svg';

        $this->withSettings($this->renderSettings([
            'defaultQrFormat' => 'svg',
        ]), function() use ($link): void {
            $options = ['format' => 'svg', 'errorCorrection' => 'H'];
            $expected = $this->explicitBaconOutput($link->getUrl(), 'svg', 'H');

            self::assertSame($expected, $link->getQrCode($options));
            self::assertSame($expected, $this->decodeDataUrl($link->getQrCodeDataUri($options)));
            self::assertStringContainsString('errorCorrection=H', $link->getQrCodeUrl($options));
            self::assertStringContainsString('errorCorrection=H', $link->getQrCodeDisplayUrl($options));
            self::assertStringContainsString('download=1', $link->getQrCodeUrl($options + ['download' => 1]));
        });
    }

    private function assertLogoOverlayForTemporaryAsset(string $volumeKind, string $format): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is not available.');
        }

        $asset = new StubQrLogoAsset($this->createLogoFile($format));
        $asset->volumeKind = $volumeKind;
        $service = new StubLogoQrCodeService();
        $service->logoAsset = $asset;
        $options = ['format' => 'png', 'size' => 220, 'margin' => 2, 'logoSize' => 18];

        $base = $this->withEffectiveImageDriver(Images::DRIVER_GD, fn(): string => $this->generateWithServiceWithoutCache($service, $options));
        $branded = $this->withEffectiveImageDriver(Images::DRIVER_GD, fn(): string => $this->generateWithServiceWithoutCache($service, $options + ['logo' => '42']));

        $this->assertValidPng($branded, 220);
        self::assertNotSame($base, $branded);
        self::assertSame($volumeKind, $asset->volumeKind);
        foreach ($asset->createdCopies as $copy) {
            self::assertFileDoesNotExist($copy);
        }
    }

    private function assertLogoFailureReturnsBasePng(?Asset $asset): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is not available.');
        }

        $service = new StubLogoQrCodeService();
        $service->logoAsset = $asset;
        $options = ['format' => 'png', 'size' => 220, 'logoSize' => 18];
        $base = $this->withEffectiveImageDriver(Images::DRIVER_GD, fn(): string => $this->generateWithServiceWithoutCache($service, $options));
        $startLevel = ob_get_level();
        $withLogo = $this->withEffectiveImageDriver(Images::DRIVER_GD, fn(): string => $this->generateWithServiceWithoutCache($service, $options + ['logo' => '42']));

        $this->assertValidPng($withLogo, 220);
        self::assertSame($base, $withLogo);
        self::assertSame($startLevel, ob_get_level());
        if ($asset instanceof StubQrLogoAsset) {
            foreach ($asset->createdCopies as $copy) {
                self::assertFileDoesNotExist($copy);
            }
        }
    }

    /** @param array<string, mixed> $settings */
    private function assertRejectedGenerationIsNotCached(QrCodeService $service, array $settings = []): void
    {
        $this->withCraftCache(function(CascadeCache $cache) use ($service, $settings): void {
            $this->withSettings($this->cacheSettings($settings), function() use ($cache, $service): void {
                try {
                    $service->generateQrCode('https://example.com/rejected-generation');
                    self::fail('Rejected generation should throw.');
                } catch (\RuntimeException) {
                    self::assertSame([], $cache->setDurations);
                }
            });
        });
    }

    /** @param array<string, mixed> $options */
    private function generateWithoutCache(array $options): string
    {
        return $this->generateWithServiceWithoutCache(ShortLinkManager::$plugin->qrCode, $options);
    }

    /** @param array<string, mixed> $options */
    private function generateWithServiceWithoutCache(QrCodeService $service, array $options): string
    {
        return $this->withSettings($this->renderSettings(), fn(): string => $service->generateQrCode('https://example.com/qr-test', $options));
    }

    /** @param array<string, mixed> $options */
    private function generateDataUrlWithoutCache(array $options): string
    {
        return $this->withSettings($this->renderSettings(), fn(): string => ShortLinkManager::$plugin->qrCode->generateQrCodeDataUrl('https://example.com/qr-test-data-url', $options));
    }

    private function withEffectiveImageDriver(string $driver, callable $callback): mixed
    {
        $original = Craft::$app->getImages();
        Craft::$app->set('images', new StubImagesService($driver));

        try {
            return $callback();
        } finally {
            Craft::$app->set('images', $original);
        }
    }

    private function withCraftCache(callable $callback): void
    {
        $original = Craft::$app->getCache();
        self::assertInstanceOf(CacheInterface::class, $original);
        $cache = new CascadeCache();
        Craft::$app->set('cache', $cache);

        try {
            $callback($cache);
        } finally {
            Craft::$app->set('cache', $original);
        }
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function cacheSettings(array $overrides = []): array
    {
        return array_merge([
            'cacheStorageMethod' => 'craft',
            'enableQrCodeCache' => true,
            'qrCodeCacheDuration' => 83,
            'defaultQrSize' => 256,
            'defaultQrColor' => '#000000',
            'defaultQrBgColor' => '#FFFFFF',
            'defaultQrFormat' => 'png',
            'defaultQrMargin' => 4,
            'defaultQrErrorCorrection' => 'M',
            'qrModuleStyle' => 'square',
            'qrEyeStyle' => 'square',
            'qrEyeColor' => null,
            'qrLogoSize' => 20,
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function renderSettings(array $overrides = []): array
    {
        return array_merge([
            'enableQrCodeCache' => false,
            'defaultQrSize' => 256,
            'defaultQrColor' => '#000000',
            'defaultQrBgColor' => '#FFFFFF',
            'defaultQrFormat' => 'png',
            'defaultQrMargin' => 4,
            'defaultQrErrorCorrection' => 'M',
            'qrModuleStyle' => 'square',
            'qrEyeStyle' => 'square',
            'qrEyeColor' => null,
            'enableQrLogo' => false,
            'qrLogoSize' => 20,
        ], $overrides);
    }

    private function cacheIdentity(QrCodeService $service, string $url): string
    {
        $method = new \ReflectionMethod($service, '_getCacheKey');

        return $method->invoke($service, $url, 256, '000000', 'FFFFFF', 'png', 4, 'M', 'square', 'square', null, null, 20);
    }

    private function explicitBaconOutput(
        string $url,
        string $format,
        string $errorCorrection,
        int $size = 256,
        int $margin = 4,
    ): string {
        $style = new RendererStyle(
            $size,
            $margin,
            SquareModule::instance(),
            SquareEye::instance(),
            Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(0, 0, 0)),
        );
        $renderer = $format === 'svg'
            ? new ImageRenderer($style, new SvgImageBackEnd())
            : QrCodeRendererHelper::createPngRenderer($style);
        $level = match ($errorCorrection) {
            'L' => ErrorCorrectionLevel::L(),
            'M' => ErrorCorrectionLevel::M(),
            'Q' => ErrorCorrectionLevel::Q(),
            'H' => ErrorCorrectionLevel::H(),
        };

        return (new Writer($renderer))->writeString(
            $url,
            Encoder::DEFAULT_BYTE_MODE_ENCODING,
            $level,
        );
    }

    private function assertValidPng(string $png, ?int $size = null): void
    {
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        self::assertStringEndsWith("\x00\x00\x00\x00IEND\xAE\x42\x60\x82", $png);
        $dimensions = getimagesizefromstring($png);
        self::assertIsArray($dimensions);
        self::assertSame('image/png', $dimensions['mime']);
        if ($size !== null) {
            self::assertSame($size, $dimensions[0]);
            self::assertSame($size, $dimensions[1]);
        }
    }

    private function assertValidSvg(string $svg, ?int $size = null): void
    {
        self::assertStringContainsString('<svg', $svg);
        self::assertStringContainsString('</svg>', $svg);
        if ($size !== null) {
            self::assertMatchesRegularExpression('/<svg[^>]+width="' . $size . '"[^>]+height="' . $size . '"/', $svg);
        }
    }

    private function decodeDataUrl(string $dataUrl): string
    {
        $encoded = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $decoded = base64_decode($encoded, true);
        self::assertIsString($decoded);

        return $decoded;
    }

    private function createLogoFile(string $format): string
    {
        $path = $this->temporaryFile('logo-' . $format, '');
        $image = imagecreatetruecolor(40, 24);
        self::assertInstanceOf(\GdImage::class, $image);
        $background = imagecolorallocate($image, 230, 20, 80);
        imagefill($image, 0, 0, $background);
        $foreground = imagecolorallocate($image, 20, 40, 220);
        imagefilledrectangle($image, 8, 4, 31, 19, $foreground);

        try {
            $written = match ($format) {
                'jpeg' => imagejpeg($image, $path),
                'png' => imagepng($image, $path),
                'gif' => imagegif($image, $path),
                'bmp' => imagebmp($image, $path),
                default => false,
            };
            self::assertTrue($written);
        } finally {
            $this->releaseGdImage($image);
        }

        return $path;
    }

    private function releaseGdImage(?\GdImage &$image): void
    {
        if (!$image instanceof \GdImage) {
            return;
        }

        if (PHP_VERSION_ID < 80500) {
            imagedestroy($image);
        }

        $image = null;
    }

    private function temporaryFile(string $prefix, string $contents): string
    {
        $path = tempnam(Craft::$app->getPath()->getTempPath(), 'sl-qr-' . $prefix . '-');
        self::assertIsString($path);
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return $path;
    }
}

final class StubImagesService extends Images
{
    public function __construct(private readonly string $effectiveDriver)
    {
        parent::__construct();
    }

    public function getIsGd(): bool
    {
        return $this->effectiveDriver === self::DRIVER_GD;
    }

    public function getIsImagick(): bool
    {
        return $this->effectiveDriver === self::DRIVER_IMAGICK;
    }
}

final class StubQrLogoAsset extends Asset
{
    /** @var list<string> */
    public array $createdCopies = [];
    public bool $throwOnCopy = false;
    public string $volumeKind = 'local';

    public function __construct(private readonly string $fixtureSourcePath)
    {
        parent::__construct();
    }

    public function getCopyOfFile(): string
    {
        if ($this->throwOnCopy) {
            throw new \RuntimeException('Fixture copy failure.');
        }

        $copy = tempnam(Craft::$app->getPath()->getTempPath(), 'sl-qr-asset-copy-');
        if (!is_string($copy) || !copy($this->fixtureSourcePath, $copy)) {
            throw new \RuntimeException('Fixture copy could not be created.');
        }
        $this->createdCopies[] = $copy;

        return $copy;
    }
}

class StubLogoQrCodeService extends QrCodeService
{
    public ?Asset $logoAsset = null;

    protected function resolveLogoAsset(string $logoId): ?Asset
    {
        return $this->logoAsset;
    }
}

final class FailingLogoEncodingQrCodeService extends StubLogoQrCodeService
{
    protected function encodeLogoPng(\GdImage $image): string|false
    {
        ob_start();

        throw new \RuntimeException('Fixture PNG encoding failure.');
    }
}

final class ThrowingQrCodeService extends QrCodeService
{
    protected function _generateQrCode(string $url, int $size, string $color, string $bgColor, string $format, int $margin, string $errorCorrection, string $moduleStyle, string $eyeStyle, ?string $eyeColor, ?string $logoId, int $logoSize): string
    {
        throw new \RuntimeException('Fixture renderer failure.');
    }
}

final class InvalidOutputQrCodeService extends QrCodeService
{
    public string $output = '';

    protected function _generateQrCode(string $url, int $size, string $color, string $bgColor, string $format, int $margin, string $errorCorrection, string $moduleStyle, string $eyeStyle, ?string $eyeColor, ?string $logoId, int $logoSize): string
    {
        return $this->output;
    }
}
