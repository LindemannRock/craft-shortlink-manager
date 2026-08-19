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
            'size' => 180,
        ]);

        $this->withSettings(['enableQrCodeCache' => false], function(): void {
            $controller = $this->controller();
            $response = $controller->actionGenerate();

            self::assertTrue($controller->loginRequired);
            self::assertSame(['shortLinkManager:editLinks'], $controller->requiredPermissions);
            self::assertSame('image/png', $response->headers->get('Content-Type'));
            self::assertStringStartsWith("\x89PNG\r\n\x1a\n", (string)$response->content);
        });
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

    public function testPublicGenerationForwardsErrorCorrection(): void
    {
        $link = $this->qrLink('sl-test-qr-public-error-correction', 'svg');
        $this->installRequest([
            'format' => 'svg',
            'errorCorrection' => 'h',
        ]);
        $service = new CapturingQrCodeService();
        $this->swapPluginComponent('shortlink-manager', 'qrCode', $service);

        $response = $this->controller()->actionGenerate($link->code);

        self::assertSame('h', $service->options['errorCorrection'] ?? null);
        self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
    }

    public function testGeneratedResponsePreservesMimeAndSignatureAcrossErrorCorrectionLevels(): void
    {
        $link = $this->qrLink('sl-test-qr-error-correction-response', 'png');

        $this->withSettings(['enableQrCodeCache' => false], function() use ($link): void {
            foreach (['L', 'M', 'Q', 'H'] as $errorCorrection) {
                foreach (['png', 'svg'] as $format) {
                    $this->installRequest([
                        'format' => $format,
                        'errorCorrection' => $errorCorrection,
                    ]);
                    $response = $this->controller()->actionGenerate($link->code);

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
