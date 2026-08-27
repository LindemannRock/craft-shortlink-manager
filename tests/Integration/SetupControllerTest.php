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
use craft\models\Site;
use craft\services\Sites;
use lindemannrock\shortlinkmanager\console\controllers\SetupController;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use yii\console\ExitCode;

/**
 * Covers global starter-template installation behavior.
 *
 * @since 5.28.3
 */
final class SetupControllerTest extends TestCase
{
    #[DataProvider('configuredTemplateProvider')]
    public function testCopyCommandUsesConfiguredGlobalDestination(
        string $templateKey,
        string $setting,
        string $configuredPath,
        string $expectedDestination,
        string $unexpectedDestination,
    ): void {
        $templatesPath = $this->createTrackedTempDirectory('sl-test-setup-command-destination');

        $this->withSettings([
            $setting => $configuredPath,
        ], function() use ($expectedDestination, $templateKey, $templatesPath, $unexpectedDestination): void {
            $exitCode = $this->withSiteTemplatesPath(
                $templatesPath,
                fn(): int => $this->runCopyCommand($templateKey),
            );

            self::assertSame(ExitCode::OK, $exitCode);
            self::assertFileExists($templatesPath . DIRECTORY_SEPARATOR . $expectedDestination);
            self::assertFileDoesNotExist($templatesPath . DIRECTORY_SEPARATOR . $unexpectedDestination);
            self::assertSame(
                file_get_contents($this->bundledTemplatePath($templateKey)),
                file_get_contents($templatesPath . DIRECTORY_SEPARATOR . $expectedDestination),
            );
        });
    }

    /**
     * @return iterable<string, array{templateKey: string, setting: string, configuredPath: string, expectedDestination: string, unexpectedDestination: string}>
     */
    public static function configuredTemplateProvider(): iterable
    {
        yield 'redirect with explicit html extension' => [
            'templateKey' => 'redirect',
            'setting' => 'redirectTemplate',
            'configuredPath' => 'custom/redirect.html',
            'expectedDestination' => 'custom/redirect.html',
            'unexpectedDestination' => 'custom/redirect.html.twig',
        ];
        yield 'expired with extensionless path' => [
            'templateKey' => 'expired',
            'setting' => 'expiredTemplate',
            'configuredPath' => 'custom/expired',
            'expectedDestination' => 'custom/expired.twig',
            'unexpectedDestination' => 'custom/expired',
        ];
        yield 'qr with extensionless path' => [
            'templateKey' => 'qr',
            'setting' => 'qrTemplate',
            'configuredPath' => 'custom/qr',
            'expectedDestination' => 'custom/qr.twig',
            'unexpectedDestination' => 'custom/qr',
        ];
    }

    public function testCopyCommandAddsGlobalFallbackForOneUnresolvedSite(): void
    {
        [$firstSite, $secondSite] = $this->twoEnabledSites();
        $templatesPath = $this->createTrackedTempDirectory('sl-test-setup-command-fallback');
        $overridePath = $templatesPath . DIRECTORY_SEPARATOR . $firstSite->handle . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'redirect.twig';
        FileHelper::createDirectory(dirname($overridePath));
        file_put_contents($overridePath, '{# first-site-override #}');

        $this->withSettings([
            'enabledSites' => [(int) $firstSite->id, (int) $secondSite->id],
            'redirectTemplate' => 'custom/redirect',
        ], function() use ($firstSite, $secondSite, $templatesPath, $overridePath): void {
            $exitCode = $this->withCraftSites(
                [$firstSite, $secondSite],
                fn(): int => $this->withSiteTemplatesPath(
                    $templatesPath,
                    fn(): int => $this->runCopyCommand('redirect'),
                ),
            );

            self::assertSame(ExitCode::OK, $exitCode);
            self::assertSame('{# first-site-override #}', file_get_contents($overridePath));
            self::assertFileDoesNotExist(
                $templatesPath . DIRECTORY_SEPARATOR . $secondSite->handle . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'redirect.twig',
            );
            self::assertSame(
                file_get_contents($this->bundledTemplatePath('redirect')),
                file_get_contents($templatesPath . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'redirect.twig'),
            );
        });
    }

    public function testCopyCommandCreatesExplicitTwigBesideSameStemHtmlAndPreservesOverrides(): void
    {
        [$firstSite, $secondSite] = $this->twoEnabledSites();
        $templatesPath = $this->createTrackedTempDirectory('sl-test-setup-command-explicit-twig');
        $globalHtmlPath = $templatesPath . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'redirect.html';
        $globalTwigPath = $templatesPath . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'redirect.twig';
        $overridePath = $templatesPath . DIRECTORY_SEPARATOR . $firstSite->handle . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'redirect.twig';
        $secondSiteOverridePath = $templatesPath . DIRECTORY_SEPARATOR . $secondSite->handle . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'redirect.twig';
        FileHelper::createDirectory(dirname($globalHtmlPath));
        FileHelper::createDirectory(dirname($overridePath));
        file_put_contents($globalHtmlPath, '<!-- existing global html -->');
        file_put_contents($overridePath, '{# existing site override #}');

        $this->withSettings([
            'enabledSites' => [(int) $firstSite->id, (int) $secondSite->id],
            'redirectTemplate' => 'custom/redirect.twig',
        ], function() use ($firstSite, $globalHtmlPath, $globalTwigPath, $overridePath, $secondSite, $secondSiteOverridePath, $templatesPath): void {
            $exitCode = $this->withCraftSites(
                [$firstSite, $secondSite],
                fn(): int => $this->withSiteTemplatesPath(
                    $templatesPath,
                    fn(): int => $this->runCopyCommand('redirect'),
                ),
            );

            self::assertSame(ExitCode::OK, $exitCode);
            self::assertSame('<!-- existing global html -->', file_get_contents($globalHtmlPath));
            self::assertSame('{# existing site override #}', file_get_contents($overridePath));
            self::assertFileDoesNotExist($secondSiteOverridePath);
            self::assertFileExists($globalTwigPath);
            self::assertSame(
                file_get_contents($this->bundledTemplatePath('redirect')),
                file_get_contents($globalTwigPath),
            );
        });
    }

    public function testCopyCommandSkipsGlobalFallbackWhenEverySiteHasAnOverride(): void
    {
        $sites = Craft::$app->getSites()->getAllSites(false);
        self::assertNotEmpty($sites, 'The integration environment must have an enabled site.');
        $templatesPath = $this->createTrackedTempDirectory('sl-test-setup-command-overrides');
        $overrideContents = [];

        foreach ($sites as $site) {
            self::assertNotNull($site->handle);
            $overridePath = $templatesPath . DIRECTORY_SEPARATOR . $site->handle . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'redirect.twig';
            $overrideContents[$overridePath] = '{# override-' . $site->handle . ' #}';
            FileHelper::createDirectory(dirname($overridePath));
            file_put_contents($overridePath, $overrideContents[$overridePath]);
        }

        $this->withSettings([
            'enabledSites' => array_map(static fn(Site $site): int => (int) $site->id, $sites),
            'redirectTemplate' => 'custom/redirect',
        ], function() use ($overrideContents, $templatesPath): void {
            $exitCode = $this->withSiteTemplatesPath(
                $templatesPath,
                fn(): int => $this->runCopyCommand('redirect', true),
            );

            self::assertSame(ExitCode::OK, $exitCode);
            self::assertFileDoesNotExist($templatesPath . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'redirect.twig');

            foreach ($overrideContents as $overridePath => $contents) {
                self::assertSame($contents, file_get_contents($overridePath));
            }
        });
    }

    public function testOverwriteReplacesOnlyTheCalculatedGlobalDestination(): void
    {
        $sites = Craft::$app->getSites()->getAllSites(false);
        self::assertNotEmpty($sites, 'The integration environment must have an enabled site.');
        $templatesPath = $this->createTrackedTempDirectory('sl-test-setup-command-overwrite');
        $globalDestination = $templatesPath . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'redirect.twig';
        FileHelper::createDirectory(dirname($globalDestination));
        file_put_contents($globalDestination, '{# old-global #}');
        $overrideContents = [];

        foreach ($sites as $site) {
            self::assertNotNull($site->handle);
            $overridePath = $templatesPath . DIRECTORY_SEPARATOR . $site->handle . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'redirect.twig';
            $overrideContents[$overridePath] = '{# protected-' . $site->handle . ' #}';
            FileHelper::createDirectory(dirname($overridePath));
            file_put_contents($overridePath, $overrideContents[$overridePath]);
        }

        $this->withSettings([
            'enabledSites' => array_map(static fn(Site $site): int => (int) $site->id, $sites),
            'redirectTemplate' => 'custom/redirect',
        ], function() use ($globalDestination, $overrideContents, $templatesPath): void {
            $exitCode = $this->withSiteTemplatesPath(
                $templatesPath,
                fn(): int => $this->runCopyCommand('redirect', true),
            );

            self::assertSame(ExitCode::OK, $exitCode);
            self::assertSame(file_get_contents($this->bundledTemplatePath('redirect')), file_get_contents($globalDestination));

            foreach ($overrideContents as $overridePath => $contents) {
                self::assertSame($contents, file_get_contents($overridePath));
            }
        });
    }

    private function runCopyCommand(string $template, bool $overwrite = false): int
    {
        $controller = new SetupController('setup', ShortLinkManager::$plugin);
        $controller->interactive = false;
        $controller->template = $template;
        $controller->overwrite = $overwrite;

        return $controller->actionCopyTemplates();
    }

    private function bundledTemplatePath(string $template): string
    {
        $projectRoot = Craft::getAlias('@root');
        self::assertIsString($projectRoot);

        return $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'lindemannrock' . DIRECTORY_SEPARATOR . 'craft-shortlink-manager' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $template . '.twig';
    }

    /**
     * @return array{0: Site, 1: Site}
     */
    private function twoEnabledSites(): array
    {
        $firstSite = Craft::$app->getSites()->getCurrentSite();
        self::assertNotNull($firstSite->id);
        self::assertNotNull($firstSite->handle);

        $secondSite = new Site([
            'id' => (int) $firstSite->id + 2000000,
            'uid' => 'sl-test-second-site-' . bin2hex(random_bytes(4)),
            'handle' => 'slTestSecondSite' . bin2hex(random_bytes(2)),
            'name' => 'ShortLink Test Second Site',
            'language' => $firstSite->language,
            'enabled' => true,
        ]);

        return [$firstSite, $secondSite];
    }

    /**
     * @template T
     * @param list<Site> $sites
     * @param callable(): T $callback
     * @return T
     */
    private function withCraftSites(array $sites, callable $callback): mixed
    {
        $originalSites = Craft::$app->getSites();
        $testSites = new class() extends Sites {
            public Site $currentSite;

            /** @var list<Site> */
            public array $sites = [];

            public function getAllSites(?bool $withDisabled = null): array
            {
                return $this->sites;
            }

            public function getCurrentSite(): Site
            {
                return $this->currentSite;
            }

            public function setCurrentSite(mixed $site): void
            {
                if (!$site instanceof Site) {
                    throw new RuntimeException('The setup command test only supports Site objects.');
                }

                $this->currentSite = $site;
            }
        };
        $testSites->currentSite = $sites[0];
        $testSites->sites = $sites;
        Craft::$app->set('sites', $testSites);

        try {
            return $callback();
        } finally {
            Craft::$app->set('sites', $originalSites);
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
}
