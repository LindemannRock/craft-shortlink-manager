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
use craft\helpers\FileHelper;
use craft\web\Request;
use craft\web\TemplateResponseFormatter;
use craft\web\View;
use lindemannrock\shortlinkmanager\controllers\QrCodeController;
use lindemannrock\shortlinkmanager\controllers\RedirectController;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\services\SetupService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\Stubs\StubDeviceDetectionService;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use yii\web\Response;

/**
 * Protects configured frontend-template rendering through Craft's deferred
 * response formatter.
 *
 * @since 5.28.4
 */
final class ConfiguredTemplateRenderingTest extends TestCase
{
    private ?object $originalRequest = null;
    private ?object $originalResponse = null;
    private ?object $originalView = null;

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
        if ($this->originalView !== null) {
            Craft::$app->set('view', $this->originalView);
            $this->originalView = null;
        }

        parent::tearDown();
    }

    #[DataProvider('environmentTemplateConsumers')]
    public function testDefinedEnvironmentTemplateExpressionRendersAtDeferredBoundary(
        string $setting,
        string $environmentName,
        string $templateName,
        string $consumer,
    ): void {
        $templatesPath = $this->createTrackedTempDirectory('sl-test-configured-template');
        $templatePath = $templatesPath . DIRECTORY_SEPARATOR . $templateName . '.twig';
        FileHelper::createDirectory(dirname($templatePath));
        self::assertNotFalse(file_put_contents($templatePath, "rendered-{$consumer}"));

        $environmentExisted = array_key_exists($environmentName, $_SERVER);
        $environmentValue = $environmentExisted ? $_SERVER[$environmentName] : null;
        $_SERVER[$environmentName] = $templateName;

        try {
            $this->withSiteTemplatesPath($templatesPath, function() use (
                $setting,
                $environmentName,
                $consumer,
                $templatesPath,
            ): void {
                $this->withSettings([
                    $setting => '$' . $environmentName,
                    'directRedirect' => false,
                    'enableAnalytics' => false,
                    'enabledIntegrations' => [],
                ], function() use ($consumer, $templatesPath): void {
                    $response = match ($consumer) {
                        'redirect' => $this->redirectResponse($this->activeLink()),
                        'expired' => $this->expiredResponse($this->expiredLink()),
                        'qr' => $this->qrDisplayResponse($this->qrLink()),
                        default => throw new \LogicException("Unknown template consumer: {$consumer}"),
                    };

                    Craft::$app->getView()->setTemplatesPath($templatesPath);
                    self::assertSame(TemplateResponseFormatter::FORMAT, $response->format);
                    $this->formatResponse($response);
                    self::assertSame("rendered-{$consumer}", $response->content);
                });
            });
        } finally {
            $this->restoreServerValue($environmentName, $environmentExisted, $environmentValue);
        }
    }

    /**
     * @return iterable<string, array{setting: string, environmentName: string, templateName: string, consumer: string}>
     */
    public static function environmentTemplateConsumers(): iterable
    {
        yield 'redirect interstitial' => [
            'setting' => 'redirectTemplate',
            'environmentName' => 'SHORTLINK_MANAGER_TEST_REDIRECT_TEMPLATE',
            'templateName' => 'configured/redirect',
            'consumer' => 'redirect',
        ];
        yield 'expired page' => [
            'setting' => 'expiredTemplate',
            'environmentName' => 'SHORTLINK_MANAGER_TEST_EXPIRED_TEMPLATE',
            'templateName' => 'configured/expired',
            'consumer' => 'expired',
        ];
        yield 'QR display page' => [
            'setting' => 'qrTemplate',
            'environmentName' => 'SHORTLINK_MANAGER_TEST_QR_TEMPLATE',
            'templateName' => 'configured/qr',
            'consumer' => 'qr',
        ];
    }

    #[DataProvider('templateConsumers')]
    public function testDirectConfiguredTemplatePathRendersAtDeferredBoundary(
        string $setting,
        string $consumer,
        string $defaultTemplate,
    ): void {
        $this->assertConfiguredTemplateRenders(
            $setting,
            $consumer,
            "configured/{$consumer}.html",
            "direct-{$consumer}",
        );
    }

    #[DataProvider('templateConsumers')]
    public function testEmptySettingRendersEstablishedDefaultAtDeferredBoundary(
        string $setting,
        string $consumer,
        string $defaultTemplate,
    ): void {
        $this->assertConfiguredTemplateRenders($setting, $consumer, $defaultTemplate, "default-{$consumer}", '');
    }

    #[DataProvider('templateConsumers')]
    public function testUnresolvedEnvironmentTemplateFailsAtDeferredBoundaryAndSetupAgrees(
        string $setting,
        string $consumer,
        string $defaultTemplate,
    ): void {
        $environmentName = 'SHORTLINK_MANAGER_TEST_MISSING_' . strtoupper($consumer) . '_TEMPLATE';
        $environmentExisted = array_key_exists($environmentName, $_SERVER);
        $environmentValue = $environmentExisted ? $_SERVER[$environmentName] : null;
        unset($_SERVER[$environmentName]);
        $templatesPath = $this->createTrackedTempDirectory('sl-test-missing-configured-template');

        try {
            $this->withSiteTemplatesPath($templatesPath, function() use (
                $setting,
                $consumer,
                $environmentName,
                $templatesPath,
            ): void {
                $this->withSettings([
                    $setting => '$' . $environmentName,
                    'directRedirect' => false,
                    'enableAnalytics' => false,
                    'enabledIntegrations' => [],
                ], function() use ($setting, $consumer, $templatesPath): void {
                    $settings = ShortLinkManager::$plugin->getSettings();
                    $status = (new SetupService())->templateStatuses($settings);
                    $indexed = array_column($status, null, 'setting');
                    self::assertSame('', $indexed[$setting]['template']);
                    self::assertFalse($indexed[$setting]['exists']);

                    $response = $this->responseForConsumer($consumer);
                    Craft::$app->getView()->setTemplatesPath($templatesPath);
                    self::assertSame(TemplateResponseFormatter::FORMAT, $response->format);

                    try {
                        $this->formatResponse($response);
                        self::fail('An unresolved configured template must fail during deferred formatting.');
                    } catch (\craft\web\twig\TemplateLoaderException $exception) {
                        self::assertStringContainsString('Unable to find the template', $exception->getMessage());
                        self::assertStringNotContainsString('shortlink-manager/', $exception->getMessage());
                    }
                });
            });
        } finally {
            $this->restoreServerValue($environmentName, $environmentExisted, $environmentValue);
        }
    }

    #[DataProvider('templateConsumers')]
    public function testSiteOverrideWinsThenGlobalFallbackRendersWithoutRequestCacheLeakage(
        string $setting,
        string $consumer,
        string $defaultTemplate,
    ): void {
        $templatesPath = $this->createTrackedTempDirectory('sl-test-template-override');
        $templateName = "configured/{$consumer}";
        $globalPath = $templatesPath . DIRECTORY_SEPARATOR . $templateName . '.twig';
        FileHelper::createDirectory(dirname($globalPath));
        self::assertNotFalse(file_put_contents($globalPath, "global-{$consumer}"));

        $this->withSiteTemplatesPath($templatesPath, function() use (
            $setting,
            $consumer,
            $templateName,
            $templatesPath,
        ): void {
            $this->withSettings([
                $setting => $templateName,
                'directRedirect' => false,
                'enableAnalytics' => false,
                'enabledIntegrations' => [],
            ], function() use ($consumer, $templateName, $templatesPath): void {
                $siteResponse = $this->responseForConsumer($consumer);
                $sitePaths = [];
                foreach (Craft::$app->getSites()->getAllSites() as $site) {
                    $sitePath = $templatesPath . DIRECTORY_SEPARATOR . $site->handle
                        . DIRECTORY_SEPARATOR . $templateName . '.twig';
                    FileHelper::createDirectory(dirname($sitePath));
                    self::assertNotFalse(file_put_contents($sitePath, "site-{$consumer}"));
                    $sitePaths[] = $sitePath;
                }
                Craft::$app->getView()->setTemplatesPath($templatesPath);
                self::assertSame(View::TEMPLATE_MODE_SITE, Craft::$app->getView()->getTemplateMode());
                $this->formatResponse($siteResponse);
                self::assertSame("site-{$consumer}", $siteResponse->content);

                foreach ($sitePaths as $sitePath) {
                    self::assertTrue(unlink($sitePath));
                }
                $globalResponse = $this->responseForConsumer($consumer);
                Craft::$app->getView()->setTemplatesPath($templatesPath);
                $this->formatResponse($globalResponse);
                self::assertSame("global-{$consumer}", $globalResponse->content);
            });
        });
    }

    /**
     * @return iterable<string, array{setting: string, consumer: string, defaultTemplate: string}>
     */
    public static function templateConsumers(): iterable
    {
        yield 'redirect interstitial' => [
            'setting' => 'redirectTemplate',
            'consumer' => 'redirect',
            'defaultTemplate' => 'shortlink-manager/redirect',
        ];
        yield 'expired page' => [
            'setting' => 'expiredTemplate',
            'consumer' => 'expired',
            'defaultTemplate' => 'shortlink-manager/expired',
        ];
        yield 'QR display page' => [
            'setting' => 'qrTemplate',
            'consumer' => 'qr',
            'defaultTemplate' => 'shortlink-manager/qr',
        ];
    }

    private function assertConfiguredTemplateRenders(
        string $setting,
        string $consumer,
        string $templateName,
        string $marker,
        ?string $configuredTemplate = null,
    ): void {
        $templatesPath = $this->createTrackedTempDirectory('sl-test-configured-template-path');
        $templatePath = $templatesPath . DIRECTORY_SEPARATOR . $templateName;
        if (pathinfo(basename($templatePath), PATHINFO_EXTENSION) === '') {
            $templatePath .= '.twig';
        }
        FileHelper::createDirectory(dirname($templatePath));
        self::assertNotFalse(file_put_contents($templatePath, $marker));

        $this->withSiteTemplatesPath($templatesPath, function() use (
            $setting,
            $consumer,
            $templateName,
            $marker,
            $configuredTemplate,
            $templatesPath,
        ): void {
            $this->withSettings([
                $setting => $configuredTemplate ?? $templateName,
                'directRedirect' => false,
                'enableAnalytics' => false,
                'enabledIntegrations' => [],
            ], function() use ($consumer, $marker, $templatesPath): void {
                $response = $this->responseForConsumer($consumer);
                Craft::$app->getView()->setTemplatesPath($templatesPath);
                self::assertSame(TemplateResponseFormatter::FORMAT, $response->format);
                $this->formatResponse($response);
                self::assertSame($marker, $response->content);
            });
        });
    }

    private function responseForConsumer(string $consumer): Response
    {
        return match ($consumer) {
            'redirect' => $this->redirectResponse($this->activeLink()),
            'expired' => $this->expiredResponse($this->expiredLink()),
            'qr' => $this->qrDisplayResponse($this->qrLink()),
            default => throw new \LogicException("Unknown template consumer: {$consumer}"),
        };
    }

    private function activeLink(): ShortLink
    {
        $link = $this->seedShortLink();
        $link->directRedirect = false;
        $link->passQueryParams = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        return $link;
    }

    private function expiredLink(): ShortLink
    {
        $link = $this->seedShortLink();
        $link->dateExpired = new \DateTime('-1 hour');
        $link->expiredRedirectUrl = null;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        return $link;
    }

    private function qrLink(): ShortLink
    {
        $link = $this->seedShortLink();
        $link->qrCodeEnabled = true;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        return $link;
    }

    private function redirectResponse(ShortLink $link): Response
    {
        $this->resetWebRuntime();
        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());

        return (new RedirectController('redirect', ShortLinkManager::$plugin))->actionIndex($link->slug);
    }

    private function expiredResponse(ShortLink $link): Response
    {
        $this->resetWebRuntime();

        return (new RedirectController('redirect', ShortLinkManager::$plugin))->actionIndex($link->slug);
    }

    private function qrDisplayResponse(ShortLink $link): Response
    {
        $this->resetWebRuntime();

        return (new QrCodeController('qr-code', ShortLinkManager::$plugin))->actionDisplay($link->slug);
    }

    private function resetWebRuntime(): void
    {
        $this->originalRequest ??= Craft::$app->get('request');
        $this->originalResponse ??= Craft::$app->get('response');
        $this->originalView ??= Craft::$app->get('view');

        Craft::$app->set('request', new ConfiguredTemplateRequest());
        Craft::$app->set('response', new Response());
        Craft::$app->set('view', new View());
    }

    private function formatResponse(Response $response): void
    {
        $outputBufferLevel = ob_get_level();

        try {
            (new TemplateResponseFormatter())->format($response);
        } finally {
            while (ob_get_level() > $outputBufferLevel) {
                ob_end_clean();
            }
        }
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withSiteTemplatesPath(string $templatesPath, callable $callback): mixed
    {
        $originalTemplatesPath = Craft::getAlias('@templates');
        self::assertIsString($originalTemplatesPath);
        Craft::setAlias('@templates', $templatesPath);

        try {
            return $callback();
        } finally {
            Craft::setAlias('@templates', $originalTemplatesPath);
        }
    }

    private function restoreServerValue(string $key, bool $existed, mixed $value): void
    {
        if ($existed) {
            $_SERVER[$key] = $value;
        } else {
            unset($_SERVER[$key]);
        }
    }
}

final class ConfiguredTemplateRequest extends Request
{
    public function getIsConsoleRequest(): bool
    {
        return false;
    }

    public function getIsCpRequest(): bool
    {
        return false;
    }

    public function getParam($name, $defaultValue = null): mixed
    {
        return $defaultValue;
    }

    public function getQueryParam($name, $defaultValue = null): mixed
    {
        return $defaultValue;
    }

    public function getQueryParams(): array
    {
        return [];
    }

    public function getUserIP(int $filterOptions = 0): ?string
    {
        return '203.0.113.42';
    }

    public function getUserAgent(): ?string
    {
        return 'LindemannRock Configured Template Test';
    }

    public function getReferrer(): ?string
    {
        return null;
    }
}
