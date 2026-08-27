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
use craft\web\Request;
use craft\web\Response;
use lindemannrock\shortlinkmanager\controllers\QrCodeController;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\services\QrCodeService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;

/**
 * @since 5.29.0
 */
#[CoversClass(QrCodeController::class)]
final class QrCodeControllerTest extends TestCase
{
    private ?object $originalRequest = null;
    private ?object $originalResponse = null;

    protected function tearDown(): void
    {
        if ($this->originalRequest !== null) {
            Craft::$app->set('request', $this->originalRequest);
            $this->originalRequest = null;
        }
        if ($this->originalResponse !== null) {
            Craft::$app->set('response', $this->originalResponse);
            $this->originalResponse = null;
        }

        parent::tearDown();
    }

    public function testPublicGenerateReturnsMatchingPngMimeAndSignature(): void
    {
        $link = $this->qrLink('sl-test-qr-public-png', 'png');
        $this->installRequest();

        $this->withSettings(['enableQrCodeCache' => false], function() use ($link): void {
            $response = $this->controller()->actionGenerate($link->code);

            self::assertSame('image/png', $response->headers->get('Content-Type'));
            self::assertStringStartsWith("\x89PNG\r\n\x1a\n", (string)$response->content);
            self::assertSame('public, max-age=86400', $response->headers->get('Cache-Control'));
        });
    }

    public function testPublicGenerateReturnsMatchingSvgMimeAndSignature(): void
    {
        $link = $this->qrLink('sl-test-qr-public-svg', 'svg');
        $this->installRequest(['format' => 'svg']);

        $this->withSettings(['enableQrCodeCache' => false], function() use ($link): void {
            $response = $this->controller()->actionGenerate($link->code);

            self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
            self::assertStringContainsString('<svg', (string)$response->content);
            self::assertStringContainsString('</svg>', (string)$response->content);
        });
    }

    public function testAuthenticatedPreviewRequiresEditPermission(): void
    {
        $this->installRequest([
            'preview' => '1',
            'url' => 'https://example.com/preview',
        ]);
        $controller = $this->controller();
        $controller->denyPermission = true;

        $this->expectException(ForbiddenHttpException::class);
        try {
            $controller->actionGenerate();
        } finally {
            self::assertTrue($controller->loginRequired);
            self::assertSame(['shortLinkManager:editLinks'], $controller->requiredPermissions);
        }
    }

    public function testAuthenticatedPreviewReturnsMatchingPngOutput(): void
    {
        $this->installRequest([
            'preview' => '1',
            'url' => 'https://example.com/preview',
            'format' => 'png',
            'size' => 256,
        ]);

        $this->withSettings(['enableQrCodeCache' => false], function(): void {
            $controller = $this->controller();
            $response = $controller->actionGenerate();

            self::assertTrue($controller->loginRequired);
            self::assertSame(['shortLinkManager:editLinks'], $controller->requiredPermissions);
            self::assertSame('image/png', $response->headers->get('Content-Type'));
            self::assertStringStartsWith("\x89PNG\r\n\x1a\n", (string)$response->content);
            $dimensions = getimagesizefromstring((string)$response->content);
            self::assertIsArray($dimensions);
            self::assertSame([256, 256], [$dimensions[0], $dimensions[1]]);
            self::assertSame('private, no-store, no-cache, must-revalidate, max-age=0', $response->headers->get('Cache-Control'));
            self::assertSame('no-cache', $response->headers->get('Pragma'));
            self::assertSame('0', $response->headers->get('Expires'));
        });
    }

    public function testSettingsPreviewUsesSubmittedSizeAndBypassesPersistentCache(): void
    {
        $this->installRequest([
            'preview' => '1',
            'url' => 'https://example.com/unsaved-preview',
            'format' => 'svg',
            'size' => 256,
        ]);
        $service = new CapturingQrCodeService();
        $this->swapPluginComponent('shortlink-manager', 'qrCode', $service);

        $this->controller()->actionGenerate();

        self::assertSame(256, $service->options['size'] ?? null);
        self::assertFalse($service->options['_cache'] ?? true);
        self::assertArrayNotHasKey('_sizeMax', $service->options);
    }

    public function testExistingLinkPreviewRemains150Pixels(): void
    {
        $link = $this->qrLink('sl-test-qr-edit-preview-size', 'svg');
        $this->installRequest([
            'linkId' => $link->id,
            'siteId' => $link->siteId,
            'size' => 256,
        ]);
        $service = new CapturingQrCodeService();
        $this->swapPluginComponent('shortlink-manager', 'qrCode', $service);

        $this->controller()->actionGenerate();

        self::assertSame(150, $service->options['size'] ?? null);
        self::assertFalse($service->options['_cache'] ?? true);
    }

    public function testAuthenticatedPreviewForwardsErrorCorrection(): void
    {
        $this->installRequest([
            'preview' => '1',
            'url' => 'https://example.com/preview-error-correction',
            'format' => 'svg',
            'errorCorrection' => ' q ',
        ]);
        $service = new CapturingQrCodeService();
        $this->swapPluginComponent('shortlink-manager', 'qrCode', $service);

        $controller = $this->controller();
        $response = $controller->actionGenerate();

        self::assertTrue($controller->loginRequired);
        self::assertSame(['shortLinkManager:editLinks'], $controller->requiredPermissions);
        self::assertSame(' q ', $service->options['errorCorrection'] ?? null);
        self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
    }

    public function testPublicGenerationUsesSavedCanonicalStyleDespiteQueryOverrides(): void
    {
        $link = $this->qrLink('sl-test-qr-public-error-correction', 'png');
        $link->qrCodeSize = 320;
        $link->qrCodeColor = '#123456';
        $link->qrCodeBgColor = '#F5E6D3';
        $link->qrCodeEyeColor = '#AA2244';
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        $this->installRequest([
            'size' => 999,
            'color' => '654321',
            'bg' => 'FFFFFF',
            'format' => 'svg',
            'errorCorrection' => 'H',
            'margin' => 1,
            'moduleStyle' => 'dots',
            'eyeStyle' => 'rounded',
            'eyeColor' => 'FFFFFF',
            'logo' => 999999,
        ]);
        $service = new CapturingQrCodeService();
        $this->swapPluginComponent('shortlink-manager', 'qrCode', $service);

        $this->withSettings([
            'defaultQrErrorCorrection' => 'M',
            'defaultQrMargin' => 4,
            'qrModuleStyle' => 'square',
            'qrEyeStyle' => 'square',
        ], function() use ($link): void {
            $response = $this->controller()->actionGenerate($link->code);

            self::assertSame('image/png', $response->headers->get('Content-Type'));
        });

        self::assertSame(320, $service->options['size'] ?? null);
        self::assertSame('123456', $service->options['color'] ?? null);
        self::assertSame('F5E6D3', $service->options['bg'] ?? null);
        self::assertSame('png', $service->options['format'] ?? null);
        self::assertSame('M', $service->options['errorCorrection'] ?? null);
        self::assertSame(4, $service->options['margin'] ?? null);
        self::assertSame('square', $service->options['moduleStyle'] ?? null);
        self::assertSame('square', $service->options['eyeStyle'] ?? null);
        self::assertSame('AA2244', $service->options['eyeColor'] ?? null);
        self::assertArrayNotHasKey('logo', $service->options);
    }

    public function testPublicCodeCannotActivateAuthenticatedModes(): void
    {
        $link = $this->qrLink('sl-test-qr-public-mode-confusion', 'png');
        $this->installRequest([
            'preview' => '1',
            'url' => 'https://attacker.example/override',
            'linkId' => $link->id,
            'format' => ['svg'],
            'size' => ['4096'],
        ]);
        $service = new CapturingQrCodeService();
        $this->swapPluginComponent('shortlink-manager', 'qrCode', $service);
        $controller = $this->controller();

        $response = $controller->actionGenerate($link->code);

        self::assertFalse($controller->loginRequired);
        self::assertSame([], $controller->requiredPermissions);
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertSame($link->qrCodeSize, $service->options['size'] ?? null);
        self::assertArrayNotHasKey('_cache', $service->options);
    }

    public function testPublicBytesAreCanonicalAcrossFormerStyleParameters(): void
    {
        $link = $this->qrLink('sl-test-qr-public-byte-equivalence', 'svg');

        $this->withSettings(['enableQrCodeCache' => false], function() use ($link): void {
            $this->installRequest();
            $baseline = (string)$this->controller()->actionGenerate($link->code)->content;

            foreach ([
                ['size' => 4096, 'color' => 'FFFFFF', 'bg' => '000000', 'format' => 'png'],
                ['margin' => -999, 'errorCorrection' => 'invalid', 'moduleStyle' => 'dots', 'eyeStyle' => 'pointed'],
                ['size' => ['2048'], 'format' => ['png'], 'logo' => ['999999']],
                ['preview' => '1', 'url' => 'https://attacker.example', 'linkId' => $link->id],
            ] as $query) {
                $this->installRequest($query);
                $response = $this->controller()->actionGenerate($link->code);
                self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
                self::assertSame($baseline, (string)$response->content);
            }
        });
    }

    public function testAuthenticatedDownloadsNormalizeSizeAndKeepFilenameTruthful(): void
    {
        $link = $this->qrLink('sl-test-qr-auth-download-sizes', 'png');
        $service = new CapturingQrCodeService();
        $this->swapPluginComponent('shortlink-manager', 'qrCode', $service);

        $this->withSettings([
            'enableQrDownload' => true,
            'qrDownloadFilename' => '{code}-qr-{size}-{format}',
        ], function() use ($link, $service): void {
            foreach ([100, 256, 512, 1024, 2048, 4096] as $size) {
                foreach (['png', 'svg'] as $format) {
                    $this->installRequest([
                        'linkId' => $link->id,
                        'siteId' => $link->siteId,
                        'download' => '1',
                        'size' => $size,
                        'format' => $format,
                    ]);
                    $response = $this->controller()->actionGenerate();

                    self::assertSame($size, $service->options['size'] ?? null);
                    self::assertSame($format, $service->options['format'] ?? null);
                    self::assertFalse($service->options['_cache'] ?? true);
                    self::assertSame(4096, $service->options['_sizeMax'] ?? null);
                    self::assertSame($format === 'svg' ? 'image/svg+xml' : 'image/png', $response->headers->get('Content-Type'));
                    self::assertStringEndsWith("-{$size}-{$format}.{$format}\"", (string)$response->headers->get('Content-Disposition'));
                    self::assertSame('private, no-store, no-cache, must-revalidate, max-age=0', $response->headers->get('Cache-Control'));
                }
            }

            foreach ([
                ['requested' => 99, 'expected' => 100],
                ['requested' => 4097, 'expected' => 4096],
                ['requested' => ['2048'], 'expected' => $link->qrCodeSize],
                ['requested' => 'not-a-size', 'expected' => $link->qrCodeSize],
            ] as $case) {
                $this->installRequest([
                    'linkId' => $link->id,
                    'download' => '1',
                    'size' => $case['requested'],
                    'format' => 'png',
                ]);
                $response = $this->controller()->actionGenerate();
                self::assertSame($case['expected'], $service->options['size']);
                self::assertStringContainsString('-' . $case['expected'] . '-png.png"', (string)$response->headers->get('Content-Disposition'));
            }
        });
    }

    public function testPublicDownloadUsesCanonicalFormatSizeAndFilename(): void
    {
        $link = $this->qrLink('sl-test-qr-public-canonical-download', 'png');
        $link->qrCodeSize = 320;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        $this->installRequest(['download' => '1', 'size' => 4096, 'format' => 'svg']);

        $this->withSettings([
            'enableQrCodeCache' => false,
            'enableQrDownload' => true,
            'qrDownloadFilename' => '{code}-qr-{size}-{format}',
        ], function() use ($link): void {
            $response = $this->controller()->actionGenerate($link->code);
            $dimensions = getimagesizefromstring((string)$response->content);

            self::assertIsArray($dimensions);
            self::assertSame([320, 320], [$dimensions[0], $dimensions[1]]);
            self::assertSame('image/png', $response->headers->get('Content-Type'));
            self::assertStringEndsWith('-320-png.png"', (string)$response->headers->get('Content-Disposition'));
            self::assertSame('public, max-age=86400', $response->headers->get('Cache-Control'));
        });
    }

    public function testAuthenticatedDownloadDisabledAndInvalidSiteRemainNotFound(): void
    {
        $link = $this->qrLink('sl-test-qr-auth-disabled', 'png');

        $this->withSettings(['enableQrDownload' => false], function() use ($link): void {
            $this->installRequest(['linkId' => $link->id, 'download' => '1']);
            try {
                $this->controller()->actionGenerate();
                self::fail('Disabled downloads must remain unavailable.');
            } catch (NotFoundHttpException) {
                self::assertTrue(true);
            }
        });

        $this->installRequest(['linkId' => $link->id, 'siteId' => 999999]);
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->actionGenerate();
    }

    public function testAuthenticatedPreviewRejectsUnsafeAndNonScalarUrls(): void
    {
        $this->installRequest(['preview' => '1', 'url' => 'javascript:alert(1)']);
        try {
            $this->controller()->actionGenerate();
            self::fail('Unsafe preview URLs must be rejected.');
        } catch (BadRequestHttpException) {
            self::assertTrue(true);
        }

        $this->installRequest(['preview' => '1', 'url' => ['https://example.com']]);
        $this->expectException(BadRequestHttpException::class);
        $this->controller()->actionGenerate();
    }

    public function testAuthenticatedLogoUsesVolumeValidatorAndDropsRejectedAsset(): void
    {
        $this->installRequest([
            'preview' => '1',
            'url' => 'https://example.com/logo-preview',
            'format' => 'svg',
            'logo' => '42',
        ]);
        $service = new CapturingQrCodeService();
        $this->swapPluginComponent('shortlink-manager', 'qrCode', $service);

        $this->withSettings(['qrLogoVolumeUid' => 'qr-volume'], function() use ($service): void {
            $controller = $this->controller();
            $controller->fixtureLogoId = 42;
            $controller->actionGenerate();
            self::assertSame('42', $controller->validatedLogo);
            self::assertSame('qr-volume', $controller->validatedVolume);
            self::assertSame(42, $service->options['logo'] ?? null);

            $controller = $this->controller();
            $controller->fixtureLogoId = null;
            $controller->actionGenerate();
            self::assertArrayNotHasKey('logo', $service->options);
        });
    }

    public function testAuthenticatedLinkRespectsQrSiteAndTrashGates(): void
    {
        $link = $this->qrLink('sl-test-qr-auth-gates', 'png');
        $link->qrCodeEnabled = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        $this->installRequest(['linkId' => $link->id]);
        try {
            $this->controller()->actionGenerate();
            self::fail('A QR-disabled link must not render through the authenticated route.');
        } catch (NotFoundHttpException) {
            self::assertTrue(true);
        }

        $link->qrCodeEnabled = true;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        $this->withSettings(['enabledSites' => [-1]], function() use ($link): void {
            $this->installRequest(['linkId' => $link->id]);
            try {
                $this->controller()->actionGenerate();
                self::fail('A site-disabled link must not render through the authenticated route.');
            } catch (NotFoundHttpException) {
                self::assertTrue(true);
            }
        });

        self::assertTrue(Craft::$app->getElements()->deleteElement($link));
        $this->installRequest(['linkId' => $link->id]);
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->actionGenerate();
    }

    public function testDisplayRouteIsInertToStyleAndModeQueries(): void
    {
        $link = $this->qrLink('sl-test-qr-display-inert', 'png');
        $this->installRequest();
        $this->swapPluginComponent('shortlink-manager', 'qrCode', new ControllerThrowingQrCodeService());
        $controller = $this->controller();
        $baseline = $controller->actionDisplay($link->code);

        $this->installRequest([
            'size' => 4096,
            'format' => 'svg',
            'preview' => '1',
            'url' => 'https://attacker.example',
            'linkId' => $link->id,
        ]);
        $response = $controller->actionDisplay($link->code);

        self::assertSame($baseline->content, $response->content);
        self::assertSame($link->id, $controller->lastTemplateVariables['shortLink']->id ?? null);
        self::assertFalse($controller->loginRequired);
        self::assertSame([], $controller->requiredPermissions);
    }

    public function testAuthenticatedResponsePreservesMimeAndSignatureAcrossErrorCorrectionLevels(): void
    {
        $this->withSettings(['enableQrCodeCache' => false], function(): void {
            foreach (['L', 'M', 'Q', 'H'] as $errorCorrection) {
                foreach (['png', 'svg'] as $format) {
                    $this->installRequest([
                        'preview' => '1',
                        'url' => 'https://example.com/error-correction-response',
                        'format' => $format,
                        'errorCorrection' => $errorCorrection,
                    ]);
                    $response = $this->controller()->actionGenerate();

                    if ($format === 'svg') {
                        self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
                        self::assertStringContainsString('<svg', (string)$response->content);
                        self::assertStringContainsString('</svg>', (string)$response->content);
                    } else {
                        self::assertSame('image/png', $response->headers->get('Content-Type'));
                        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", (string)$response->content);
                        self::assertStringEndsWith("\x00\x00\x00\x00IEND\xAE\x42\x60\x82", (string)$response->content);
                    }
                }
            }
        });
    }

    public function testDownloadUsesNormalizedFormatAndSafeFilename(): void
    {
        $link = $this->qrLink('sl-test-qr-download', 'png');
        $this->installRequest([
            'format' => 'invalid',
            'download' => '1',
        ]);

        $this->withSettings([
            'enableQrCodeCache' => false,
            'enableQrDownload' => true,
            'defaultQrFormat' => 'png',
            'qrDownloadFilename' => '../{code}-qr-{size}-{format}',
        ], function() use ($link): void {
            $response = $this->controller()->actionGenerate($link->code);
            $disposition = (string)$response->headers->get('Content-Disposition');

            self::assertSame('image/png', $response->headers->get('Content-Type'));
            self::assertStringEndsWith('-png.png"', $disposition);
            self::assertStringNotContainsString('../', $disposition);
            self::assertStringContainsString($link->code, $disposition);
        });
    }

    public function testMissingLinkRemainsNotFound(): void
    {
        $this->installRequest();
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->actionGenerate('sl-test-qr-does-not-exist');
    }

    public function testRendererFailureReturnsServerErrorAndIsLogged(): void
    {
        $link = $this->qrLink('sl-test-qr-renderer-failure', 'png');
        $this->installRequest();
        $this->swapPluginComponent('shortlink-manager', 'qrCode', new ControllerThrowingQrCodeService());
        $controller = $this->controller();

        try {
            $controller->actionGenerate($link->code);
            self::fail('Renderer failure should return a server error.');
        } catch (ServerErrorHttpException $e) {
            self::assertSame('QR code generation failed.', $e->getMessage());
            self::assertNotEmpty($controller->loggedErrors);
            self::assertSame('Failed to generate QR code', $controller->loggedErrors[0]['message']);
            self::assertSame('png', $controller->loggedErrors[0]['params']['format']);
        }
    }

    private function qrLink(string $code, string $format): ShortLink
    {
        $link = $this->seedShortLink([
            'code' => $code,
            'slug' => $code,
            'destinationUrl' => 'https://example.com/' . $code,
        ]);
        $link->qrCodeEnabled = true;
        $link->qrCodeFormat = $format;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        return $link;
    }

    /** @param array<string, mixed> $queryParams */
    private function installRequest(array $queryParams = []): void
    {
        if ($this->originalRequest === null) {
            $this->originalRequest = Craft::$app->get('request');
        }
        if ($this->originalResponse === null) {
            $this->originalResponse = Craft::$app->get('response');
        }

        Craft::$app->set('request', new QrControllerRequest($queryParams));
        Craft::$app->set('response', new Response());
    }

    private function controller(): TestQrCodeController
    {
        return new TestQrCodeController('qr-code', ShortLinkManager::$plugin);
    }
}

final class TestQrCodeController extends QrCodeController
{
    public bool $loginRequired = false;
    public bool $denyPermission = false;

    /** @var list<string> */
    public array $requiredPermissions = [];

    /** @var list<array{message: string, params: array<string, mixed>}> */
    public array $loggedErrors = [];

    /** @var array<string, mixed> */
    public array $lastTemplateVariables = [];

    public mixed $validatedLogo = null;
    public ?string $validatedVolume = null;
    public ?int $fixtureLogoId = null;

    public function requireLogin(): void
    {
        $this->loginRequired = true;
    }

    public function requirePermission(string $permissionName): void
    {
        $this->requiredPermissions[] = $permissionName;
        if ($this->denyPermission) {
            throw new ForbiddenHttpException('Fixture permission denied.');
        }
    }

    /** @param array<string, mixed> $params */
    protected function logError(string $message, array $params = []): void
    {
        $this->loggedErrors[] = ['message' => $message, 'params' => $params];
    }

    /** @param array<string, mixed> $variables */
    public function renderTemplate(string $template, array $variables = [], ?string $templateMode = null): Response
    {
        $this->lastTemplateVariables = $variables;
        $response = Craft::$app->getResponse();
        $response->content = 'rendered:' . $template;

        return $response;
    }

    protected function validatePreviewLogoId(mixed $logoId, ?string $allowedVolumeUid): ?int
    {
        $this->validatedLogo = $logoId;
        $this->validatedVolume = $allowedVolumeUid;

        return $this->fixtureLogoId;
    }
}

final class QrControllerRequest extends Request
{
    /** @param array<string, mixed> $fixtureQueryParams */
    public function __construct(private readonly array $fixtureQueryParams)
    {
        parent::__construct();
    }

    public function getQueryParams(): array
    {
        return $this->fixtureQueryParams;
    }

    public function getQueryParam($name, $defaultValue = null): mixed
    {
        return $this->fixtureQueryParams[$name] ?? $defaultValue;
    }

    public function getIsAjax(): bool
    {
        return false;
    }
}

final class ControllerThrowingQrCodeService extends QrCodeService
{
    public function generateQrCode(string $url, array $options = []): string
    {
        throw new \RuntimeException('Fixture renderer failure details.');
    }
}

final class CapturingQrCodeService extends QrCodeService
{
    /** @var array<string, mixed> */
    public array $options = [];

    public function generateQrCode(string $url, array $options = []): string
    {
        $this->options = $options;

        return '<svg xmlns="http://www.w3.org/2000/svg"></svg>';
    }
}
