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
use craft\console\Request as ConsoleRequest;
use craft\web\Request as WebRequest;
use lindemannrock\shortlinkmanager\controllers\RedirectController;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\Stubs\StubDeviceDetectionService;
use lindemannrock\shortlinkmanager\tests\TestCase;
use yii\web\Response;

/**
 * Pins the global/per-link direct redirect decision from GH #27/#30.
 *
 * @since 5.20.0
 */
final class DirectRedirectTest extends TestCase
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

    public function testGlobalDirectRedirectIssuesHttpRedirectAndIncrementsHits(): void
    {
        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $this->installRequest();
        $link = $this->seedShortLink([
            'code' => 'sl-test-direct-global',
            'slug' => 'sl-test-direct-global',
            'destinationUrl' => 'https://example.com/direct-global',
        ]);
        $link->passQueryParams = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        $this->withSettings([
            'directRedirect' => true,
            'enableAnalytics' => false,
        ], function() use ($link): void {
            $response = $this->controller()->actionIndex($link->slug);

            self::assertSame(302, $response->getStatusCode());
            self::assertSame('https://example.com/direct-global', $response->getHeaders()->get('Location'));
            self::assertSame(1, $this->fetchHitsFromDb((int) $link->id));
        });
    }

    public function testDirectRedirectWithAnalyticsSendsNoStoreHeaders(): void
    {
        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $this->installWebRequest();
        $link = $this->seedShortLink([
            'code' => 'sl-test-direct-cache',
            'slug' => 'sl-test-direct-cache',
            'destinationUrl' => 'https://example.com/direct-cache',
            'trackAnalytics' => true,
        ]);
        $link->passQueryParams = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        $this->withSettings([
            'directRedirect' => true,
            'enableAnalytics' => true,
        ], function() use ($link): void {
            $response = $this->controller()->actionIndex($link->slug);

            self::assertSame(302, $response->getStatusCode());
            self::assertSame('https://example.com/direct-cache', $response->getHeaders()->get('Location'));
            self::assertSame('no-store, no-cache, must-revalidate, max-age=0', $response->headers->get('Cache-Control'));
            self::assertSame('no-cache', $response->headers->get('Pragma'));
            self::assertSame('0', $response->headers->get('Expires'));
        });
    }

    public function testGlobalDirectRedirectFalseOverridesPerLinkTrue(): void
    {
        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $this->installRequest();
        $link = $this->seedShortLink([
            'code' => 'sl-test-direct-link',
            'slug' => 'sl-test-direct-link',
            'destinationUrl' => 'https://example.com/direct-link',
        ]);
        $link->directRedirect = true;
        $link->passQueryParams = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        $this->withSettings([
            'directRedirect' => false,
            'redirectTemplate' => 'shortlink-manager/redirect',
            'enableAnalytics' => false,
        ], function() use ($link): void {
            $response = $this->controller()->actionIndex($link->slug);

            self::assertSame(200, $response->getStatusCode());
            self::assertSame('rendered:shortlink-manager/redirect', $response->content);
            self::assertSame(0, $this->fetchHitsFromDb((int) $link->id));
        });
    }

    public function testPerLinkDirectRedirectFalseOverridesGlobalTrue(): void
    {
        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $this->installRequest();
        $link = $this->seedShortLink([
            'code' => 'sl-test-template-link',
            'slug' => 'sl-test-template-link',
            'destinationUrl' => 'https://example.com/template-link',
        ]);
        $link->directRedirect = false;
        $link->passQueryParams = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        $this->withSettings([
            'directRedirect' => true,
            'redirectTemplate' => 'shortlink-manager/redirect',
            'enableAnalytics' => false,
        ], function() use ($link): void {
            $response = $this->controller()->actionIndex($link->slug);

            self::assertSame(200, $response->getStatusCode());
            self::assertSame('rendered:shortlink-manager/redirect', $response->content);
            self::assertSame(0, $this->fetchHitsFromDb((int) $link->id));
        });
    }

    public function testRenderedRedirectWithAnalyticsSendsNoStoreHeaders(): void
    {
        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $this->installRequest();
        $link = $this->seedShortLink([
            'code' => 'sl-test-render-cache',
            'slug' => 'sl-test-render-cache',
            'destinationUrl' => 'https://example.com/render-cache',
            'trackAnalytics' => true,
        ]);
        $link->directRedirect = false;
        $link->passQueryParams = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        $this->withSettings([
            'directRedirect' => true,
            'redirectTemplate' => 'shortlink-manager/redirect',
            'enableAnalytics' => true,
        ], function() use ($link): void {
            $response = $this->controller()->actionIndex($link->slug);

            self::assertSame(200, $response->getStatusCode());
            self::assertSame('rendered:shortlink-manager/redirect', $response->content);
            self::assertSame('no-store, no-cache, must-revalidate, max-age=0', $response->headers->get('Cache-Control'));
            self::assertSame('no-cache', $response->headers->get('Pragma'));
            self::assertSame('0', $response->headers->get('Expires'));
        });
    }

    public function testRenderedRedirectForwardsOnlyTheOriginalVisitorQueryOnTheTrackedHop(): void
    {
        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $visitorQuery = [
            'existing' => 'visitor',
            'campaign' => 'summer',
            'code' => 'visitor-code',
            'site' => 'visitor-site',
            'filters' => ['status' => ['new', 'active']],
            '__sl_query' => 'visitor-namespace-value',
            'p' => 'visitor-path',
            'src' => 'qr',
            'debug' => '1',
        ];
        $this->installWebRequest($visitorQuery);
        $link = $this->seedShortLink([
            'code' => 'sl-test-rendered-query',
            'slug' => 'sl-test-rendered-query',
            'destinationUrl' => 'https://example.com/rendered?existing=destination&kept=1#details',
            'trackAnalytics' => true,
        ]);
        $link->directRedirect = false;
        $link->passQueryParams = true;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        $site = Craft::$app->getSites()->getSiteById($link->siteId);
        self::assertNotNull($site);

        $this->withSettings([
            'directRedirect' => true,
            'redirectTemplate' => 'shortlink-manager/redirect',
            'enableAnalytics' => true,
            'enabledIntegrations' => [],
        ], function() use ($link, $site): void {
            $controller = $this->controller();
            $landing = $controller->actionIndex($link->slug, $site->handle);

            self::assertSame(200, $landing->getStatusCode());
            self::assertSame(0, $this->fetchHitsFromDb((int)$link->id));
            self::assertSame(0, $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]));

            $goUrl = (string)$controller->lastVariables['goUrl'];
            $goQuery = [];
            parse_str((string)parse_url($goUrl, PHP_URL_QUERY), $goQuery);
            self::assertSame($link->slug, $goQuery['code'] ?? null);
            self::assertSame($site->handle, $goQuery['site'] ?? null);
            self::assertSame('qr', $goQuery['src'] ?? null);

            $this->installWebRequest($goQuery);
            $redirect = $this->controller()->actionGo($link->slug);
            self::assertSame(302, $redirect->getStatusCode());

            $location = (string)$redirect->headers->get('Location');
            self::assertSame('details', parse_url($location, PHP_URL_FRAGMENT));
            $destinationQuery = [];
            parse_str((string)parse_url($location, PHP_URL_QUERY), $destinationQuery);
            self::assertSame([
                'existing' => 'visitor',
                'kept' => '1',
                'campaign' => 'summer',
                'code' => 'visitor-code',
                'site' => 'visitor-site',
                'filters' => ['status' => ['new', 'active']],
                '__sl_query' => 'visitor-namespace-value',
            ], $destinationQuery);
            self::assertArrayNotHasKey('p', $destinationQuery);
            self::assertArrayNotHasKey('src', $destinationQuery);
            self::assertArrayNotHasKey('debug', $destinationQuery);
            self::assertSame(1, $this->fetchHitsFromDb((int)$link->id));
            self::assertSame(1, $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]));
        });
    }

    public function testDirectRedirectPassThroughPreservesVisitorPrecedenceAndUrlShapes(): void
    {
        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());

        foreach ([
            'absolute' => 'https://example.com/path?existing=destination#details',
            'relative' => '/path?existing=destination#details',
        ] as $label => $destinationUrl) {
            $this->installWebRequest([
                'existing' => 'visitor',
                'nested' => ['value' => ['one', 'two']],
                'p' => 'visitor-path',
                'src' => 'qr',
                'debug' => '1',
            ]);
            $link = $this->seedShortLink([
                'destinationUrl' => $destinationUrl,
            ]);
            $link->passQueryParams = true;
            self::assertTrue(Craft::$app->getElements()->saveElement($link));

            $this->withSettings([
                'directRedirect' => true,
                'enableAnalytics' => false,
            ], function() use ($label, $link): void {
                $response = $this->controller()->actionIndex($link->slug);

                self::assertSame(302, $response->getStatusCode(), $label);
                $location = (string)$response->getHeaders()->get('Location');
                self::assertSame('details', parse_url($location, PHP_URL_FRAGMENT), $label);
                $query = [];
                parse_str((string)parse_url($location, PHP_URL_QUERY), $query);
                self::assertSame([
                    'existing' => 'visitor',
                    'nested' => ['value' => ['one', 'two']],
                ], $query, $label);
                self::assertSame(1, $this->fetchHitsFromDb((int)$link->id), $label);
            });
        }
    }

    public function testGoActionIgnoresMissingMalformedAndScalarVisitorTransport(): void
    {
        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $link = $this->seedShortLink([
            'destinationUrl' => 'https://example.com/go?existing=destination#details',
        ]);
        $link->passQueryParams = true;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        $site = Craft::$app->getSites()->getSiteById($link->siteId);
        self::assertNotNull($site);

        foreach ([
            'missing' => [
                'code' => $link->slug,
                'site' => $site->handle,
                'p' => 'actions/shortlink-manager/redirect/go',
                'src' => 'qr',
                'debug' => '1',
            ],
            'scalar-wrapper' => ['__sl_query' => 'visitor-controlled'],
            'scalar-payload' => ['__sl_query' => ['params' => 'visitor-controlled']],
        ] as $label => $query) {
            $this->installWebRequest($query);
            $response = $this->controller()->actionGo($link->slug, $site->handle);

            self::assertSame(302, $response->getStatusCode(), $label);
            self::assertSame(
                'https://example.com/go?existing=destination#details',
                $response->getHeaders()->get('Location'),
                $label,
            );
        }

        self::assertSame(3, $this->fetchHitsFromDb((int)$link->id));
    }

    public function testRenderedRedirectGoUrlUsesConfiguredBaseUrlWithSiteToken(): void
    {
        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $this->installRequest();
        $link = $this->seedShortLink([
            'code' => 'sl-test-go-token',
            'slug' => 'sl-test-go-token',
            'destinationUrl' => 'https://example.com/go-token',
        ]);
        $link->directRedirect = false;
        $link->passQueryParams = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        $site = Craft::$app->getSites()->getSiteById($link->siteId);
        self::assertNotNull($site);

        $this->withSettings([
            'directRedirect' => true,
            'redirectTemplate' => 'shortlink-manager/redirect',
            'enableAnalytics' => false,
            'shortlinkBaseUrl' => 'https://short.example/{siteHandle}',
        ], function() use ($link, $site): void {
            $controller = $this->controller();
            $response = $controller->actionIndex($link->slug);

            self::assertSame(200, $response->getStatusCode());
            self::assertSame('rendered:shortlink-manager/redirect', $response->content);
            self::assertStringStartsWith('https://short.example/index.php?', (string) $controller->lastVariables['goUrl']);
            self::assertStringContainsString('p=actions/shortlink-manager/redirect/go', (string) $controller->lastVariables['goUrl']);
            self::assertStringContainsString('code=' . $link->slug, (string) $controller->lastVariables['goUrl']);
            self::assertStringContainsString('site=' . $site->handle, (string) $controller->lastVariables['goUrl']);
            self::assertStringContainsString('src=direct', (string) $controller->lastVariables['goUrl']);
            self::assertStringNotContainsString('/' . $site->handle . '/index.php', (string) $controller->lastVariables['goUrl']);
            self::assertStringNotContainsString('craftcms.ddev.site', (string) $controller->lastVariables['goUrl']);
        });
    }

    public function testRenderedRedirectGoUrlAddsSiteParamWhenConfiguredBaseUrlHasNoSiteToken(): void
    {
        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $this->installRequest();
        $link = $this->seedShortLink([
            'code' => 'sl-test-go-shared',
            'slug' => 'sl-test-go-shared',
            'destinationUrl' => 'https://example.com/go-shared',
        ]);
        $link->directRedirect = false;
        $link->passQueryParams = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        $site = Craft::$app->getSites()->getSiteById($link->siteId);
        self::assertNotNull($site);

        $this->withSettings([
            'directRedirect' => true,
            'redirectTemplate' => 'shortlink-manager/redirect',
            'enableAnalytics' => false,
            'shortlinkBaseUrl' => 'https://short.example',
        ], function() use ($link, $site): void {
            $controller = $this->controller();
            $response = $controller->actionIndex($link->slug);

            self::assertSame(200, $response->getStatusCode());
            self::assertStringStartsWith('https://short.example/index.php?', (string) $controller->lastVariables['goUrl']);
            self::assertStringContainsString('p=actions/shortlink-manager/redirect/go', (string) $controller->lastVariables['goUrl']);
            self::assertStringContainsString('code=' . $link->slug, (string) $controller->lastVariables['goUrl']);
            self::assertStringContainsString('site=' . $site->handle, (string) $controller->lastVariables['goUrl']);
            self::assertStringContainsString('src=direct', (string) $controller->lastVariables['goUrl']);
            self::assertStringNotContainsString('craftcms.ddev.site', (string) $controller->lastVariables['goUrl']);
        });
    }

    private function controller(): TestRedirectController
    {
        return new TestRedirectController('redirect', ShortLinkManager::$plugin);
    }

    private function installRequest(): void
    {
        if ($this->originalRequest === null) {
            $this->originalRequest = Craft::$app->get('request');
        }
        if ($this->originalResponse === null) {
            $this->originalResponse = Craft::$app->get('response');
        }

        Craft::$app->set('request', new TestConsoleRequest());
        Craft::$app->set('response', new \craft\web\Response());
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function installWebRequest(array $queryParams = []): void
    {
        if ($this->originalRequest === null) {
            $this->originalRequest = Craft::$app->get('request');
        }
        if ($this->originalResponse === null) {
            $this->originalResponse = Craft::$app->get('response');
        }

        Craft::$app->set('request', new TestWebRequest($queryParams));
        Craft::$app->set('response', new \craft\web\Response());
    }
}

final class TestRedirectController extends RedirectController
{
    /**
     * @var array<string, mixed>
     */
    public array $lastVariables = [];

    /**
     * @param array<string, mixed> $variables
     */
    public function renderTemplate(string $template, array $variables = [], ?string $templateMode = null): Response
    {
        $this->lastVariables = $variables;

        $response = new Response();
        $response->setStatusCode(200);
        $response->content = "rendered:{$template}";

        return $response;
    }
}

final class TestConsoleRequest extends ConsoleRequest
{
    public function getParam($name, $defaultValue = null): mixed
    {
        return $defaultValue;
    }

    public function getIsAjax(): bool
    {
        return false;
    }
}

final class TestWebRequest extends WebRequest
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

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getIsAjax(): bool
    {
        return false;
    }

    public function getUserIP(int $filterOptions = 0): ?string
    {
        return '203.0.113.42';
    }

    public function getUserAgent(): ?string
    {
        return 'Mozilla/5.0 (Test) LindemannRockStub/1.0';
    }

    public function getReferrer(): ?string
    {
        return 'https://example.com/some/page';
    }
}
