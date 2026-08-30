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
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\FileHelper;
use craft\models\Site;
use craft\services\Sites;
use craft\web\View;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\services\SetupService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use yii\base\Event;

/**
 * Pins setup-readiness detection.
 *
 * Covers the template-existence resolution (which must match how Craft resolves
 * the frontend templates at render time for every enabled site) and the IP salt
 * readiness gate (which must mirror the runtime hash gate in
 * AnalyticsTrackingService).
 *
 * @since 5.27.0
 */
final class SetupServiceTest extends TestCase
{
    private SetupService $setup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setup = ShortLinkManager::$plugin->setup;
    }

    public function testTemplateStatusDetectsExtensionAndIndexVariants(): void
    {
        $templatesPath = Craft::$app->getPath()->getSiteTemplatesPath();
        $dir = 'sl-test-setup-' . bin2hex(random_bytes(4));
        $absDir = $templatesPath . DIRECTORY_SEPARATOR . $dir;
        FileHelper::createDirectory($absDir);

        try {
            // Direct .twig file: templates/<dir>/redirect.twig
            file_put_contents($absDir . DIRECTORY_SEPARATOR . 'redirect.twig', '{# test #}');
            // Direct .html file: templates/<dir>/expired.html
            file_put_contents($absDir . DIRECTORY_SEPARATOR . 'expired.html', '<!-- test -->');
            // Directory-style index template: templates/<dir>/qr/index.twig
            FileHelper::createDirectory($absDir . DIRECTORY_SEPARATOR . 'qr');
            file_put_contents($absDir . DIRECTORY_SEPARATOR . 'qr' . DIRECTORY_SEPARATOR . 'index.twig', '{# test #}');

            $this->withSettings([
                'redirectTemplate' => $dir . '/redirect',
                'expiredTemplate' => $dir . '/expired',
                'qrTemplate' => $dir . '/qr',
            ], function(): void {
                $settings = ShortLinkManager::$plugin->getSettings();
                $bySetting = $this->indexBySetting($this->setup->templateStatuses($settings));

                self::assertTrue(
                    $bySetting['redirectTemplate']['exists'],
                    'A .twig template must be detected as present.',
                );

                if (in_array('html', Craft::$app->getConfig()->getGeneral()->defaultTemplateExtensions, true)) {
                    self::assertTrue(
                        $bySetting['expiredTemplate']['exists'],
                        'A .html template must be detected when html is a configured template extension.',
                    );
                }

                self::assertTrue(
                    $bySetting['qrTemplate']['exists'],
                    'A directory-style index.twig template must be detected as present.',
                );
            });
        } finally {
            FileHelper::removeDirectory($absDir);
        }
    }

    public function testTemplateStatusReportsMissingTemplate(): void
    {
        $dir = 'sl-test-setup-missing-' . bin2hex(random_bytes(4));

        $this->withSettings([
            'redirectTemplate' => $dir . '/redirect',
        ], function(): void {
            $settings = ShortLinkManager::$plugin->getSettings();
            $bySetting = $this->indexBySetting($this->setup->templateStatuses($settings));

            self::assertFalse(
                $bySetting['redirectTemplate']['exists'],
                'A template with no matching file must be reported as missing.',
            );
        });
    }

    public function testTemplateStatusDetectsExplicitAndExtensionlessPaths(): void
    {
        $templatesPath = Craft::$app->getPath()->getSiteTemplatesPath();
        $dir = 'sl-test-setup-exact-' . bin2hex(random_bytes(4));
        $absDir = $templatesPath . DIRECTORY_SEPARATOR . $dir;
        FileHelper::createDirectory($absDir);

        try {
            file_put_contents($absDir . DIRECTORY_SEPARATOR . 'redirect.html', '<!-- test -->');
            file_put_contents($absDir . DIRECTORY_SEPARATOR . 'expired', '{# test #}');

            $this->withSettings([
                'redirectTemplate' => $dir . '/redirect.html',
                'expiredTemplate' => $dir . '/expired',
            ], function() use ($dir): void {
                $settings = ShortLinkManager::$plugin->getSettings();
                $bySetting = $this->indexBySetting($this->setup->templateStatuses($settings));

                self::assertTrue(
                    $bySetting['redirectTemplate']['exists'],
                    'An explicitly configured .html template must be detected at its exact path.',
                );
                self::assertSame(
                    'templates/' . $dir . '/redirect.html',
                    $bySetting['redirectTemplate']['destination'],
                    'The copy destination must not append .twig to an explicit extension.',
                );
                self::assertTrue($bySetting['redirectTemplate']['destinationExists']);

                self::assertTrue(
                    $bySetting['expiredTemplate']['exists'],
                    'An exact extensionless template must be detected before extension variants.',
                );
                self::assertSame(
                    'templates/' . $dir . '/expired.twig',
                    $bySetting['expiredTemplate']['destination'],
                    'An extensionless configured path must keep the standard .twig copy destination.',
                );
                self::assertFalse($bySetting['expiredTemplate']['destinationExists']);
            });
        } finally {
            FileHelper::removeDirectory($absDir);
        }
    }

    public function testExplicitTwigPathDoesNotResolveSameStemHtmlTemplate(): void
    {
        $templatesPath = $this->createTrackedTempDirectory('sl-test-setup-explicit-twig');
        $configuredDir = $templatesPath . DIRECTORY_SEPARATOR . 'custom';
        $configuredPath = 'custom/redirect.twig';
        FileHelper::createDirectory($configuredDir);
        file_put_contents($configuredDir . DIRECTORY_SEPARATOR . 'redirect.html', '<!-- same stem -->');

        $runtimeExists = $this->withSiteTemplatesPath(
            $templatesPath,
            static fn(): bool => (new View())->doesTemplateExist($configuredPath, View::TEMPLATE_MODE_SITE),
        );
        $statuses = $this->withSiteTemplatesPath(
            $templatesPath,
            fn(): array => $this->setup->templateStatuses(new Settings([
                'redirectTemplate' => $configuredPath,
            ])),
        );
        $bySetting = $this->indexBySetting($statuses);

        self::assertFalse(
            $runtimeExists,
            'Craft runtime resolution must not satisfy an explicit .twig path with a same-stem .html file.',
        );
        self::assertFalse(
            $bySetting['redirectTemplate']['exists'],
            'Setup readiness must report the same explicit .twig path as missing.',
        );
        self::assertSame('templates/custom/redirect.twig', $bySetting['redirectTemplate']['destination']);
        self::assertFalse($bySetting['redirectTemplate']['destinationExists']);
        self::assertFileExists($configuredDir . DIRECTORY_SEPARATOR . 'redirect.html');
    }

    public function testTemplateStatusRequiresEveryEnabledSiteToResolve(): void
    {
        $sites = Craft::$app->getSites();
        $enabledSites = $sites->getAllSites(false);
        self::assertNotEmpty($enabledSites, 'The integration environment must have an enabled site.');

        $templatesPath = Craft::$app->getPath()->getSiteTemplatesPath();
        $dir = 'sl-test-setup-sites-' . bin2hex(random_bytes(4));
        $createdSiteRoots = [];
        $originalState = $this->captureCallerSiteState();

        try {
            foreach ($enabledSites as $site) {
                $siteRoot = $templatesPath . DIRECTORY_SEPARATOR . $site->handle;
                if (!is_dir($siteRoot)) {
                    $createdSiteRoots[] = $siteRoot;
                }

                $overrideDir = $siteRoot . DIRECTORY_SEPARATOR . $dir;
                FileHelper::createDirectory($overrideDir);
                file_put_contents($overrideDir . DIRECTORY_SEPARATOR . 'redirect.twig', '{# ' . $site->handle . ' #}');
            }

            $this->withSettings([
                'redirectTemplate' => $dir . '/redirect',
            ], function() use ($dir, $enabledSites, $originalState, $templatesPath): void {
                $settings = ShortLinkManager::$plugin->getSettings();
                $bySetting = $this->indexBySetting($this->setup->templateStatuses($settings));

                self::assertTrue(
                    $bySetting['redirectTemplate']['exists'],
                    'Per-site overrides must satisfy readiness when every enabled site resolves the template.',
                );
                self::assertSame(
                    'templates/' . $dir . '/redirect.twig',
                    $bySetting['redirectTemplate']['destination'],
                    'Per-site overrides must not change the global fallback copy destination.',
                );
                self::assertFalse($bySetting['redirectTemplate']['destinationExists']);
                $this->assertCallerSiteState($originalState);

                $missingSite = $enabledSites[array_key_last($enabledSites)];
                FileHelper::removeDirectory(
                    $templatesPath . DIRECTORY_SEPARATOR . $missingSite->handle . DIRECTORY_SEPARATOR . $dir,
                );

                $bySetting = $this->indexBySetting($this->setup->templateStatuses($settings));
                self::assertFalse(
                    $bySetting['redirectTemplate']['exists'],
                    'One unresolved enabled site must keep global setup readiness incomplete.',
                );
                $this->assertCallerSiteState($originalState);
            });

            $firstSite = $enabledSites[0];
            $firstOverrideDir = $templatesPath . DIRECTORY_SEPARATOR . $firstSite->handle . DIRECTORY_SEPARATOR . $dir;
            FileHelper::createDirectory($firstOverrideDir);
            file_put_contents($firstOverrideDir . DIRECTORY_SEPARATOR . 'redirect.twig', '{# selected site #}');

            $this->withSettings([
                'enabledSites' => [(int) $firstSite->id],
                'redirectTemplate' => $dir . '/redirect',
            ], function() use ($originalState): void {
                $settings = ShortLinkManager::$plugin->getSettings();
                $bySetting = $this->indexBySetting($this->setup->templateStatuses($settings));

                self::assertTrue(
                    $bySetting['redirectTemplate']['exists'],
                    'A Craft site excluded from ShortLink Manager must not block setup readiness.',
                );

                $this->assertCallerSiteState($originalState);
            });
        } finally {
            foreach ($enabledSites as $site) {
                FileHelper::removeDirectory(
                    $templatesPath . DIRECTORY_SEPARATOR . $site->handle . DIRECTORY_SEPARATOR . $dir,
                );
            }

            foreach (array_reverse($createdSiteRoots) as $siteRoot) {
                @rmdir($siteRoot);
            }

            $this->restoreCallerSiteState($originalState);
        }
    }

    #[DataProvider('templateReadinessStateProvider')]
    public function testTemplateChecksRestoreCallerSiteState(bool $ready, bool $serverValuesPresent): void
    {
        $templatesPath = $this->createTrackedTempDirectory('sl-test-setup-state');
        $configuredDir = $templatesPath . DIRECTORY_SEPARATOR . 'configured';

        if ($ready) {
            FileHelper::createDirectory($configuredDir);
            file_put_contents($configuredDir . DIRECTORY_SEPARATOR . 'redirect.twig', '{# redirect #}');
            file_put_contents($configuredDir . DIRECTORY_SEPARATOR . 'expired.twig', '{# expired #}');
            file_put_contents($configuredDir . DIRECTORY_SEPARATOR . 'qr.twig', '{# qr #}');
        }

        $runnerState = $this->captureCallerSiteState();

        try {
            if ($serverValuesPresent) {
                $_SERVER['CRAFT_SITE'] = 'sl-test-site-sentinel';
                $_SERVER['CRAFT_SITE_UPPER'] = 'SL_TEST_SITE_UPPER_SENTINEL';
            } else {
                unset($_SERVER['CRAFT_SITE'], $_SERVER['CRAFT_SITE_UPPER']);
            }

            $expectedState = $this->captureCallerSiteState();
            $settings = new Settings([
                'redirectTemplate' => 'configured/redirect',
                'expiredTemplate' => 'configured/expired',
                'qrTemplate' => 'configured/qr',
            ]);

            $statuses = $this->withSiteTemplatesPath(
                $templatesPath,
                fn(): array => $this->setup->templateStatuses($settings),
            );

            foreach ($statuses as $status) {
                self::assertSame(
                    $ready,
                    $status['exists'],
                    $ready
                        ? 'Every configured template must resolve in the ready scenario.'
                        : 'Every configured template must remain missing in the missing scenario.',
                );
            }

            $this->assertCallerSiteState($expectedState);
        } finally {
            $this->restoreCallerSiteState($runnerState);
        }
    }

    /**
     * @return iterable<string, array{ready: bool, serverValuesPresent: bool}>
     */
    public static function templateReadinessStateProvider(): iterable
    {
        yield 'ready with server sentinels' => [
            'ready' => true,
            'serverValuesPresent' => true,
        ];
        yield 'missing with server sentinels' => [
            'ready' => false,
            'serverValuesPresent' => true,
        ];
        yield 'ready with absent server values' => [
            'ready' => true,
            'serverValuesPresent' => false,
        ];
        yield 'missing with absent server values' => [
            'ready' => false,
            'serverValuesPresent' => false,
        ];
    }

    public function testEnvironmentBackedTemplateStatusUsesResolvedPathsAndRestoresCallerState(): void
    {
        $templatesPath = $this->createTrackedTempDirectory('sl-test-setup-env');
        $configuredDir = $templatesPath . DIRECTORY_SEPARATOR . 'configured';
        FileHelper::createDirectory($configuredDir);
        foreach (['redirect', 'expired', 'qr'] as $template) {
            self::assertNotFalse(file_put_contents(
                $configuredDir . DIRECTORY_SEPARATOR . $template . '.twig',
                "{# {$template} #}",
            ));
        }

        $environment = [
            'SHORTLINK_MANAGER_TEST_SETUP_REDIRECT_TEMPLATE' => 'configured/redirect',
            'SHORTLINK_MANAGER_TEST_SETUP_EXPIRED_TEMPLATE' => 'configured/expired',
            'SHORTLINK_MANAGER_TEST_SETUP_QR_TEMPLATE' => 'configured/qr',
        ];
        $originalEnvironment = [];
        foreach ($environment as $name => $value) {
            $originalEnvironment[$name] = [array_key_exists($name, $_SERVER), $_SERVER[$name] ?? null];
            $_SERVER[$name] = $value;
        }
        $callerState = $this->captureCallerSiteState();

        try {
            $settings = new Settings([
                'redirectTemplate' => '$SHORTLINK_MANAGER_TEST_SETUP_REDIRECT_TEMPLATE',
                'expiredTemplate' => '$SHORTLINK_MANAGER_TEST_SETUP_EXPIRED_TEMPLATE',
                'qrTemplate' => '$SHORTLINK_MANAGER_TEST_SETUP_QR_TEMPLATE',
            ]);
            $statuses = $this->withSiteTemplatesPath(
                $templatesPath,
                fn(): array => $this->setup->templateStatuses($settings),
            );
            $bySetting = $this->indexBySetting($statuses);

            foreach ([
                'redirectTemplate' => 'configured/redirect',
                'expiredTemplate' => 'configured/expired',
                'qrTemplate' => 'configured/qr',
            ] as $setting => $resolvedPath) {
                self::assertSame($resolvedPath, $bySetting[$setting]['template']);
                self::assertTrue($bySetting[$setting]['exists']);
            }
            self::assertSame('$SHORTLINK_MANAGER_TEST_SETUP_REDIRECT_TEMPLATE', $settings->redirectTemplate);
            self::assertSame('$SHORTLINK_MANAGER_TEST_SETUP_EXPIRED_TEMPLATE', $settings->expiredTemplate);
            self::assertSame('$SHORTLINK_MANAGER_TEST_SETUP_QR_TEMPLATE', $settings->qrTemplate);
            $this->assertCallerSiteState($callerState);
        } finally {
            foreach ($originalEnvironment as $name => [$existed, $value]) {
                if ($existed) {
                    $_SERVER[$name] = $value;
                } else {
                    unset($_SERVER[$name]);
                }
            }
            $this->restoreCallerSiteState($callerState);
        }
    }

    #[DataProvider('nonCopyableTemplateProvider')]
    public function testEnvironmentBackedNonCopyablePathsReturnInertReadinessStatus(
        string $setting,
        string $environmentName,
        ?string $environmentValue,
    ): void {
        $serverExisted = array_key_exists($environmentName, $_SERVER);
        $serverValue = $serverExisted ? $_SERVER[$environmentName] : null;
        $callerState = $this->captureCallerSiteState();
        $templatesPath = $this->createTrackedTempDirectory('sl-test-setup-non-copyable');
        $neighborPath = $templatesPath . DIRECTORY_SEPARATOR . 'neighbor.txt';
        self::assertSame(14, file_put_contents($neighborPath, "neighbor\0bytes"));
        self::assertTrue(chmod($neighborPath, 0640));
        $neighborBytes = file_get_contents($neighborPath);
        $neighborMode = fileperms($neighborPath);

        try {
            if ($environmentValue === null) {
                unset($_SERVER[$environmentName]);
            } else {
                $_SERVER[$environmentName] = $environmentValue;
            }

            $settings = new Settings([$setting => '$' . $environmentName]);
            $statuses = $this->withSiteTemplatesPath(
                $templatesPath,
                fn(): array => $this->setup->templateStatuses($settings),
            );
            $status = $this->indexBySetting($statuses)[$setting];

            self::assertSame($environmentValue ?? '', $status['template']);
            self::assertFalse($status['copyable']);
            self::assertSame('', $status['destination']);
            self::assertSame('', $status['destinationDir']);
            self::assertFalse($status['destinationDirExists']);
            self::assertFalse($status['destinationExists']);
            self::assertFalse($status['exists']);
            self::assertFalse($this->setup->getStatus($settings)['templatesReady']);
            self::assertSame('$' . $environmentName, $settings->{$setting});
            self::assertSame($neighborBytes, file_get_contents($neighborPath));
            self::assertSame($neighborMode, fileperms($neighborPath));
            self::assertSame([$neighborPath], glob($templatesPath . DIRECTORY_SEPARATOR . '*'));
            $this->assertCallerSiteState($callerState);
        } finally {
            $this->restoreServerValue($environmentName, $serverExisted, $serverValue);
            $this->restoreCallerSiteState($callerState);
        }
    }

    /**
     * @return iterable<string, array{setting: string, environmentName: string, environmentValue: string|null}>
     */
    public static function nonCopyableTemplateProvider(): iterable
    {
        foreach ([
            'redirect' => 'redirectTemplate',
            'expired' => 'expiredTemplate',
            'QR' => 'qrTemplate',
        ] as $label => $setting) {
            foreach ([
                'undefined' => null,
                'empty' => '',
                'parent traversal' => '../outside-template',
                'nested traversal' => 'nested/../../outside-template',
            ] as $state => $value) {
                $environmentName = 'SHORTLINK_MANAGER_TEST_NON_COPYABLE_'
                    . strtoupper($label)
                    . '_'
                    . strtoupper(str_replace(' ', '_', $state));
                yield $label . ' ' . $state => [
                    'setting' => $setting,
                    'environmentName' => $environmentName,
                    'environmentValue' => $value,
                ];
            }
        }
    }

    public function testTemplateResolutionExceptionRestoresCallerSiteState(): void
    {
        $templatesPath = $this->createTrackedTempDirectory('sl-test-setup-exception');
        $runnerState = $this->captureCallerSiteState();
        $handler = static function(RegisterTemplateRootsEvent $event): void {
            throw new RuntimeException('sl-test-template-resolution-failure');
        };
        Event::on(View::class, View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, $handler);

        try {
            $_SERVER['CRAFT_SITE'] = 'sl-test-exception-site-sentinel';
            $_SERVER['CRAFT_SITE_UPPER'] = 'SL_TEST_EXCEPTION_SITE_UPPER_SENTINEL';
            $expectedState = $this->captureCallerSiteState();
            $settings = new Settings([
                'redirectTemplate' => 'configured/missing-redirect',
            ]);

            try {
                $this->withSiteTemplatesPath(
                    $templatesPath,
                    fn(): array => $this->setup->templateStatuses($settings),
                );
                self::fail('The registered site-template resolver failure must propagate.');
            } catch (RuntimeException $exception) {
                self::assertSame('sl-test-template-resolution-failure', $exception->getMessage());
            }

            $this->assertCallerSiteState($expectedState);
        } finally {
            Event::off(View::class, View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, $handler);
            $this->restoreCallerSiteState($runnerState);
        }
    }

    public function testCraftDisabledSiteIsExcludedFromTemplateReadiness(): void
    {
        $realSites = Craft::$app->getSites();
        $enabledSite = $realSites->getCurrentSite();
        self::assertNotNull($enabledSite->id);
        self::assertNotNull($enabledSite->handle);

        $disabledSite = new Site([
            'id' => (int) $enabledSite->id + 1000000,
            'uid' => 'sl-test-disabled-site-' . bin2hex(random_bytes(4)),
            'handle' => 'slTestDisabledSite' . bin2hex(random_bytes(2)),
            'name' => 'ShortLink Test Disabled Site',
            'language' => $enabledSite->language,
            'enabled' => false,
        ]);

        $testSites = new class() extends Sites {
            public Site $currentSite;

            /** @var list<Site> */
            public array $enabledSites = [];

            /** @var list<Site> */
            public array $allSites = [];

            public function getAllSites(?bool $withDisabled = null): array
            {
                return $withDisabled === false ? $this->enabledSites : $this->allSites;
            }

            public function getCurrentSite(): Site
            {
                return $this->currentSite;
            }

            public function setCurrentSite(mixed $site): void
            {
                if (!$site instanceof Site) {
                    throw new RuntimeException('The setup readiness test only supports Site objects.');
                }

                $this->currentSite = $site;
            }
        };
        $testSites->currentSite = $enabledSite;
        $testSites->enabledSites = [$enabledSite];
        $testSites->allSites = [$enabledSite, $disabledSite];

        $templatesPath = $this->createTrackedTempDirectory('sl-test-setup-disabled-site');
        $overrideDir = $templatesPath . DIRECTORY_SEPARATOR . $enabledSite->handle . DIRECTORY_SEPARATOR . 'configured';
        FileHelper::createDirectory($overrideDir);
        file_put_contents($overrideDir . DIRECTORY_SEPARATOR . 'redirect.twig', '{# enabled site #}');

        $settings = new Settings([
            'enabledSites' => [(int) $enabledSite->id, (int) $disabledSite->id],
            'redirectTemplate' => 'configured/redirect',
        ]);
        $runnerState = $this->captureCallerSiteState();

        try {
            Craft::$app->set('sites', $testSites);
            $statuses = $this->withSiteTemplatesPath(
                $templatesPath,
                fn(): array => $this->setup->templateStatuses($settings),
            );
            $bySetting = $this->indexBySetting($statuses);

            self::assertTrue(
                $bySetting['redirectTemplate']['exists'],
                'A Craft-disabled site must not participate even when ShortLink Manager still names its site ID.',
            );
            self::assertSame($enabledSite, $testSites->getCurrentSite());
        } finally {
            Craft::$app->set('sites', $realSites);
            $this->restoreCallerSiteState($runnerState);
        }
    }

    public function testIpSaltConfiguredWhenSaltPresent(): void
    {
        $this->withSettings([
            'ipHashSalt' => str_repeat('a', 40),
        ], function(): void {
            self::assertTrue(
                $this->setup->isIpSaltConfigured(ShortLinkManager::$plugin->getSettings()),
                'A real salt value must count as configured.',
            );
        });
    }

    public function testIpSaltNotConfiguredWhenEmpty(): void
    {
        $this->withSettings([
            'ipHashSalt' => '',
        ], function(): void {
            self::assertFalse(
                $this->setup->isIpSaltConfigured(ShortLinkManager::$plugin->getSettings()),
                'An empty salt must not count as configured.',
            );
        });
    }

    public function testIpSaltNotConfiguredForUnresolvedPlaceholder(): void
    {
        $this->withSettings([
            'ipHashSalt' => '$SHORTLINK_MANAGER_IP_SALT',
        ], function(): void {
            self::assertFalse(
                $this->setup->isIpSaltConfigured(ShortLinkManager::$plugin->getSettings()),
                'The unresolved default env placeholder must not count as configured.',
            );
        });
    }

    /**
     * @param array<int, array{setting: string, template: string, destination: string, destinationDir: string, destinationDirExists: bool, destinationExists: bool, exists: bool, copyable: bool}> $statuses
     * @return array<string, array{setting: string, template: string, destination: string, destinationDir: string, destinationDirExists: bool, destinationExists: bool, exists: bool, copyable: bool}>
     */
    private function indexBySetting(array $statuses): array
    {
        $indexed = [];
        foreach ($statuses as $status) {
            $indexed[$status['setting']] = $status;
        }

        return $indexed;
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

    /**
     * @return array{site: Site, language: string, craftSiteExisted: bool, craftSite: mixed, craftSiteUpperExisted: bool, craftSiteUpper: mixed}
     */
    private function captureCallerSiteState(): array
    {
        $craftSiteExisted = array_key_exists('CRAFT_SITE', $_SERVER);
        $craftSiteUpperExisted = array_key_exists('CRAFT_SITE_UPPER', $_SERVER);

        return [
            'site' => Craft::$app->getSites()->getCurrentSite(),
            'language' => Craft::$app->language,
            'craftSiteExisted' => $craftSiteExisted,
            'craftSite' => $craftSiteExisted ? $_SERVER['CRAFT_SITE'] : null,
            'craftSiteUpperExisted' => $craftSiteUpperExisted,
            'craftSiteUpper' => $craftSiteUpperExisted ? $_SERVER['CRAFT_SITE_UPPER'] : null,
        ];
    }

    /**
     * @param array{site: Site, language: string, craftSiteExisted: bool, craftSite: mixed, craftSiteUpperExisted: bool, craftSiteUpper: mixed} $state
     */
    private function assertCallerSiteState(array $state): void
    {
        self::assertSame($state['site'], Craft::$app->getSites()->getCurrentSite());
        self::assertSame($state['language'], Craft::$app->language);

        if ($state['craftSiteExisted']) {
            self::assertArrayHasKey('CRAFT_SITE', $_SERVER);
            self::assertSame($state['craftSite'], $_SERVER['CRAFT_SITE']);
        } else {
            self::assertArrayNotHasKey('CRAFT_SITE', $_SERVER);
        }

        if ($state['craftSiteUpperExisted']) {
            self::assertArrayHasKey('CRAFT_SITE_UPPER', $_SERVER);
            self::assertSame($state['craftSiteUpper'], $_SERVER['CRAFT_SITE_UPPER']);
        } else {
            self::assertArrayNotHasKey('CRAFT_SITE_UPPER', $_SERVER);
        }
    }

    /**
     * @param array{site: Site, language: string, craftSiteExisted: bool, craftSite: mixed, craftSiteUpperExisted: bool, craftSiteUpper: mixed} $state
     */
    private function restoreCallerSiteState(array $state): void
    {
        try {
            Craft::$app->getSites()->setCurrentSite($state['site']);
        } finally {
            Craft::$app->language = $state['language'];
            $this->restoreServerValue('CRAFT_SITE', $state['craftSiteExisted'], $state['craftSite']);
            $this->restoreServerValue('CRAFT_SITE_UPPER', $state['craftSiteUpperExisted'], $state['craftSiteUpper']);
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
