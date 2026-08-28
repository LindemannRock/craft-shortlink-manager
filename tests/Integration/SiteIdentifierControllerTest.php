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
use craft\models\Site;
use craft\web\Request;
use lindemannrock\shortlinkmanager\controllers\QrCodeController;
use lindemannrock\shortlinkmanager\controllers\RedirectController;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\Stubs\StubDeviceDetectionService;
use lindemannrock\shortlinkmanager\tests\TestCase;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Protects deterministic site-aware redirect and QR controller resolution.
 */
final class SiteIdentifierControllerTest extends TestCase
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

    public function testRedirectAndGoActionsResolveHandleIdAndUidToTheExactSite(): void
    {
        $targetSite = $this->secondarySite();
        $link = $this->seedShortLink([
            'siteId' => $targetSite->id,
            'destinationUrl' => 'https://example.com/site-identifier-redirect',
        ]);
        $link->directRedirect = true;
        $link->passQueryParams = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $this->installRequest();

        $this->withSettings([
            'directRedirect' => true,
            'enableAnalytics' => false,
            'enabledIntegrations' => [],
        ], function() use ($link, $targetSite): void {
            foreach ($this->identifiers($targetSite) as $identifier) {
                $redirect = $this->redirectController()->actionIndex($link->slug, $identifier);
                self::assertSame(302, $redirect->getStatusCode());
                self::assertSame('https://example.com/site-identifier-redirect', $redirect->headers->get('Location'));

                $go = $this->redirectController()->actionGo($link->slug, $identifier);
                self::assertSame(302, $go->getStatusCode());
                self::assertSame('https://example.com/site-identifier-redirect', $go->headers->get('Location'));
            }

            $this->installRequest(['site' => $targetSite->handle]);
            $actionQuery = $this->redirectController()->actionGo($link->slug);
            self::assertSame('https://example.com/site-identifier-redirect', $actionQuery->headers->get('Location'));
        });
    }

    public function testRedirectIdentifiersSelectTheMatchingSiteVariant(): void
    {
        $targetSite = $this->secondarySite();
        $otherSite = Craft::$app->getSites()->getPrimarySite();
        $link = $this->seedShortLink([
            'siteId' => $targetSite->id,
            'destinationUrl' => 'https://example.com/wrong-site-must-not-resolve',
        ]);
        $this->setDestinationForSite($link, $targetSite, 'https://example.com/secondary-site-variant');
        $this->setDestinationForSite($link, $otherSite, 'https://example.com/primary-site-variant');

        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $this->installRequest();

        $this->withSettings([
            'directRedirect' => true,
            'enableAnalytics' => false,
            'enabledIntegrations' => [],
            'notFoundRedirectUrl' => '/',
        ], function() use ($link, $otherSite): void {
            foreach ($this->identifiers($otherSite) as $identifier) {
                $response = $this->redirectController()->actionIndex($link->slug, $identifier);
                self::assertSame('https://example.com/primary-site-variant', $response->headers->get('Location'));
            }
        });
    }

    public function testRenderedRedirectUsesTheResolvedSiteContextAndHandleQueryGoUrl(): void
    {
        $targetSite = $this->secondarySite();
        $link = $this->seedShortLink(['siteId' => $targetSite->id]);
        $link->directRedirect = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $this->installRequest();

        $this->withSettings([
            'directRedirect' => true,
            'enableAnalytics' => false,
            'enabledIntegrations' => [],
            'shortlinkBaseUrl' => 'https://short.example/{siteUid}',
        ], function() use ($link, $targetSite): void {
            $response = (new SiteIdentifierRedirectController('redirect', ShortLinkManager::$plugin))
                ->actionIndex($link->slug, $targetSite->uid);

            self::assertSame(200, $response->getStatusCode());
            self::assertSame(
                'rendered-site:' . $targetSite->id . ':context:' . $targetSite->id,
                $response->content,
            );
            self::assertStringContainsString('site=' . $targetSite->handle, $response->headers->get('X-Go-Url'));
            self::assertStringNotContainsString('/' . $targetSite->uid . '/index.php', $response->headers->get('X-Go-Url'));
        });
    }

    public function testRedirectRejectsUnknownIdentifiersDisabledSitesAndDisabledOrMissingLinks(): void
    {
        $targetSite = $this->secondarySite();
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $link = $this->seedShortLink(['siteId' => $targetSite->id]);

        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $this->installRequest();

        $this->withSettings([
            'directRedirect' => true,
            'enableAnalytics' => false,
            'enabledIntegrations' => [],
            'enabledSites' => [$primarySite->id],
            'notFoundRedirectUrl' => '/',
        ], function() use ($link, $targetSite): void {
            self::assertSame('/', $this->redirectController()->actionIndex($link->slug, $targetSite->uid)->headers->get('Location'));
        });

        $this->withSettings([
            'directRedirect' => true,
            'enableAnalytics' => false,
            'enabledIntegrations' => [],
            'enabledSites' => [],
            'notFoundRedirectUrl' => '/',
        ], function() use ($link, $targetSite): void {
            foreach (['999999999', '00000000-0000-4000-8000-000000000000'] as $unknown) {
                self::assertSame('/', $this->redirectController()->actionIndex($link->slug, $unknown)->headers->get('Location'));
            }
            self::assertSame('/', $this->redirectController()->actionIndex('sl-test-missing-link', $targetSite->uid)->headers->get('Location'));
        });

        $link->setEnabledForSite(false);
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        $this->withSettings([
            'directRedirect' => true,
            'enableAnalytics' => false,
            'enabledIntegrations' => [],
            'notFoundRedirectUrl' => '/',
        ], function() use ($link, $targetSite): void {
            self::assertSame('/', $this->redirectController()->actionIndex($link->slug, $targetSite->handle)->headers->get('Location'));
        });
    }

    public function testQrImageAndDisplayActionsResolveAllIdentifiersWithCanonicalBytes(): void
    {
        $targetSite = $this->secondarySite();
        $link = $this->seedShortLink(['siteId' => $targetSite->id]);
        $this->installRequest();

        $imageBytes = [];
        foreach ($this->identifiers($targetSite) as $identifier) {
            $this->resetResponse();
            $response = $this->qrController()->actionGenerate($link->slug, $identifier);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('image/png', $response->headers->get('Content-Type'));
            $imageBytes[] = (string)$response->content;

            $this->resetResponse();
            $display = $this->qrController()->actionDisplay($link->slug, $identifier);
            self::assertSame(200, $display->getStatusCode());
            self::assertSame('rendered-site:' . $targetSite->id . ':context:' . $targetSite->id, $display->content);
        }

        self::assertNotSame('', $imageBytes[0]);
        self::assertSame($imageBytes[0], $imageBytes[1]);
        self::assertSame($imageBytes[0], $imageBytes[2]);
    }

    public function testExplicitQrIdentifierDoesNotFallBackAndFailureGatesRemainIntact(): void
    {
        $targetSite = $this->secondarySite();
        $otherSite = Craft::$app->getSites()->getPrimarySite();
        $link = $this->seedShortLink(['siteId' => $targetSite->id]);
        $this->installRequest();

        foreach ($this->identifiers($otherSite) as $identifier) {
            $this->resetResponse();
            self::assertSame(200, $this->qrController()->actionGenerate($link->slug, $identifier)->getStatusCode());
            $this->resetResponse();
            self::assertSame(
                'rendered-site:' . $otherSite->id . ':context:' . $otherSite->id,
                $this->qrController()->actionDisplay($link->slug, $identifier)->content,
            );
        }

        foreach (['999999999', '00000000-0000-4000-8000-000000000000'] as $unknown) {
            $this->expectQrNotFound(fn(): Response => $this->qrController()->actionGenerate($link->slug, $unknown));
            $this->expectQrNotFound(fn(): Response => $this->qrController()->actionDisplay($link->slug, $unknown));
        }

        $this->expectQrNotFound(fn(): Response => $this->qrController()->actionGenerate('sl-test-missing-qr', $targetSite->uid));
        $this->expectQrNotFound(fn(): Response => $this->qrController()->actionDisplay('sl-test-missing-qr', $targetSite->uid));

        $link->qrCodeEnabled = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        $notFoundUrl = ShortLinkManager::$plugin->getSettings()->getResolvedNotFoundRedirectUrl();
        $this->resetResponse();
        self::assertSame($notFoundUrl, $this->qrController()->actionGenerate($link->slug, (string)$targetSite->id)->headers->get('Location'));
        $this->resetResponse();
        self::assertSame($notFoundUrl, $this->qrController()->actionDisplay($link->slug, (string)$targetSite->id)->headers->get('Location'));
    }

    public function testQrRoutesRejectPluginDisabledSite(): void
    {
        $targetSite = $this->secondarySite();
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $link = $this->seedShortLink(['siteId' => $targetSite->id]);
        $this->installRequest();

        $this->withSettings([
            'enabledSites' => [$primarySite->id],
            'notFoundRedirectUrl' => '/',
        ], function() use ($link, $targetSite): void {
            $this->resetResponse();
            self::assertSame('/', $this->qrController()->actionGenerate($link->slug, $targetSite->uid)->headers->get('Location'));
            $this->resetResponse();
            self::assertSame('/', $this->qrController()->actionDisplay($link->slug, $targetSite->uid)->headers->get('Location'));
        });
    }

    private function secondarySite(): Site
    {
        $primaryId = Craft::$app->getSites()->getPrimarySite()->id;
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            if ($site->id !== $primaryId) {
                return $site;
            }
        }

        self::fail('Site identifier routing tests require at least two sites.');
    }

    private function setDestinationForSite(ShortLink $link, Site $site, string $destinationUrl): void
    {
        $variant = ShortLink::find()
            ->id($link->id)
            ->siteId($site->id)
            ->status(null)
            ->one();
        self::assertInstanceOf(ShortLink::class, $variant);
        $variant->destinationUrl = $destinationUrl;
        self::assertTrue(Craft::$app->getElements()->saveElement($variant));
    }

    /**
     * @return list<string>
     */
    private function identifiers(Site $site): array
    {
        return [$site->handle, (string)$site->id, $site->uid];
    }

    private function redirectController(): RedirectController
    {
        return new RedirectController('redirect', ShortLinkManager::$plugin);
    }

    private function qrController(): SiteIdentifierQrController
    {
        return new SiteIdentifierQrController('qr-code', ShortLinkManager::$plugin);
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function installRequest(array $queryParams = []): void
    {
        if ($this->originalRequest === null) {
            $this->originalRequest = Craft::$app->get('request');
        }
        if ($this->originalResponse === null) {
            $this->originalResponse = Craft::$app->get('response');
        }

        Craft::$app->set('request', new SiteIdentifierRequest($queryParams));
        $this->resetResponse();
    }

    private function resetResponse(): void
    {
        Craft::$app->set('response', new Response());
    }

    /**
     * @param callable(): Response $callback
     */
    private function expectQrNotFound(callable $callback): void
    {
        try {
            $callback();
            self::fail('Expected the QR request to fail with NotFoundHttpException.');
        } catch (NotFoundHttpException) {
            self::assertTrue(true);
        }
    }
}

final class SiteIdentifierQrController extends QrCodeController
{
    /**
     * @param array<string, mixed> $variables
     */
    public function renderTemplate(string $template, array $variables = [], ?string $templateMode = null): Response
    {
        $shortLink = $variables['shortLink'] ?? null;
        $currentSite = $variables['currentSite'] ?? null;

        $response = new Response();
        $response->setStatusCode(200);
        $response->content = $shortLink instanceof ShortLink && $currentSite instanceof Site
            ? 'rendered-site:' . $shortLink->siteId . ':context:' . $currentSite->id
            : 'rendered-without-link';

        return $response;
    }
}

final class SiteIdentifierRedirectController extends RedirectController
{
    /**
     * @param array<string, mixed> $variables
     */
    public function renderTemplate(string $template, array $variables = [], ?string $templateMode = null): Response
    {
        $shortLink = $variables['shortLink'] ?? null;
        $currentSite = $variables['currentSite'] ?? null;

        $response = new Response();
        $response->setStatusCode(200);
        $response->content = $shortLink instanceof ShortLink && $currentSite instanceof Site
            ? 'rendered-site:' . $shortLink->siteId . ':context:' . $currentSite->id
            : 'rendered-without-link';
        $response->headers->set('X-Go-Url', (string)($variables['goUrl'] ?? ''));

        return $response;
    }
}

final class SiteIdentifierRequest extends Request
{
    /**
     * @param array<string, mixed> $queryParams
     */
    public function __construct(private readonly array $queryParams = [])
    {
        parent::__construct();
    }

    public function getParam($name, $defaultValue = null): mixed
    {
        return $this->queryParams[$name] ?? $defaultValue;
    }

    public function getQueryParam($name, $defaultValue = null): mixed
    {
        return $this->queryParams[$name] ?? $defaultValue;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getIsAjax(): bool
    {
        return false;
    }

    public function getUrl(): string
    {
        return '/site-identifier-test';
    }
}
