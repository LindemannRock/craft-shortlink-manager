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

    public function testPerLinkDirectRedirectTrueOverridesGlobalFalse(): void
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
            'enableAnalytics' => false,
        ], function() use ($link): void {
            $response = $this->controller()->actionIndex($link->slug);

            self::assertSame(302, $response->getStatusCode());
            self::assertSame('https://example.com/direct-link', $response->getHeaders()->get('Location'));
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
}

final class TestRedirectController extends RedirectController
{
    /**
     * @param array<string, mixed> $variables
     */
    public function renderTemplate(string $template, array $variables = [], ?string $templateMode = null): Response
    {
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
