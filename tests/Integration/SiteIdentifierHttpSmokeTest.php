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
use craft\models\GqlSchema;
use craft\models\GqlToken;
use craft\models\Site;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Runs generated site-identifier URLs through an owned disposable HTTP server.
 */
final class SiteIdentifierHttpSmokeTest extends TestCase
{
    /** @var resource|null */
    private $serverProcess = null;

    /** @var array<int, resource> */
    private array $serverPipes = [];

    private ?string $configPath = null;
    private ?string $templatePath = null;
    /** @var list<string> */
    private array $runtimeTemplatePaths = [];
    private ?string $cookieJarPath = null;
    private ?string $graphqlScopePath = null;

    protected function tearDown(): void
    {
        try {
            $this->stopServer();
            if ($this->configPath !== null && is_file($this->configPath)) {
                unlink($this->configPath);
            }
            if ($this->templatePath !== null && is_file($this->templatePath)) {
                unlink($this->templatePath);
            }
            foreach ($this->runtimeTemplatePaths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if ($this->cookieJarPath !== null && is_file($this->cookieJarPath)) {
                unlink($this->cookieJarPath);
            }
            if ($this->graphqlScopePath !== null && is_file($this->graphqlScopePath)) {
                unlink($this->graphqlScopePath);
            }
            DateFormatHelper::clearConfigCache('shortlink-manager');
        } finally {
            parent::tearDown();
        }
    }

    public function testGeneratedHandleIdAndUidUrlsReachExactSiteOverHttp(): void
    {
        $projectRoot = $this->disposableProjectRoot();
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $targetSite = $this->secondarySite();
        $link = $this->seedShortLink([
            'siteId' => $targetSite->id,
            'destinationUrl' => 'https://destination.example/secondary-site',
        ]);
        $link->directRedirect = true;
        $link->passQueryParams = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        $this->setDestinationForSite($link, $primarySite, 'https://destination.example/primary-site');
        $this->prepareGraphqlServiceConfig($projectRoot, $link, $primarySite, $targetSite);

        $port = $this->availablePort();
        $origin = "http://127.0.0.1:{$port}";
        $this->writeConfig($projectRoot, $origin . '/{siteHandle}');
        $this->writeRuntimeTemplates($projectRoot, $targetSite);
        $this->startServer($projectRoot, $port);

        $this->cookieJarPath = $projectRoot . '/shortlink-http-cookie.jar';
        $login = $this->request(
            $origin . '/index.php?p=actions/users/login',
            post: [
                'loginName' => 'fixture-admin',
                'password' => 'ShortLink-Fixture-Password-2026!',
            ],
            cookieJar: $this->cookieJarPath,
        );
        self::assertSame(302, $login['status'], $login['body'] . "\n" . $this->serverOutput());

        $rawTemplates = [
            'redirectTemplate' => '$SHORTLINK_MANAGER_HTTP_REDIRECT_TEMPLATE',
            'expiredTemplate' => '$SHORTLINK_MANAGER_HTTP_EXPIRED_TEMPLATE',
            'qrTemplate' => '$SHORTLINK_MANAGER_HTTP_QR_TEMPLATE',
        ];
        $save = $this->saveTemplateSettings($origin, $rawTemplates);
        self::assertSame(302, $save['status'], $save['body'] . "\n" . $this->fixtureLogOutput($projectRoot));

        $general = $this->request(
            $origin . '/admin/shortlink-manager/settings/general',
            cookieJar: $this->cookieJarPath,
        );
        self::assertSame(200, $general['status'], $general['body'] . "\n" . $this->fixtureLogOutput($projectRoot));
        foreach ($rawTemplates as $rawTemplate) {
            self::assertStringContainsString($rawTemplate, html_entity_decode($general['body']));
        }
        self::assertStringContainsString('no-store', (string)$general['cacheControl']);

        $setup = $this->request(
            $origin . '/admin/shortlink-manager/setup',
            cookieJar: $this->cookieJarPath,
        );
        self::assertSame(200, $setup['status'], $setup['body'] . "\n" . $this->fixtureLogOutput($projectRoot));
        self::assertStringContainsString('Starter templates are ready.', $setup['body']);
        foreach (['redirect', 'expired', 'qr'] as $template) {
            self::assertStringContainsString("templates/shortlink-http/{$template}.twig", $setup['body']);
        }

        $evidence = [];
        foreach ([
            'handle' => ['token' => '{siteHandle}', 'identifier' => $targetSite->handle],
            'id' => ['token' => '{siteId}', 'identifier' => (string)$targetSite->id],
            'uid' => ['token' => '{siteUid}', 'identifier' => $targetSite->uid],
        ] as $label => $case) {
            $this->restartServerWithConfig($projectRoot, $port, $origin . '/' . $case['token']);
            $siteLink = ShortLink::find()->id($link->id)->siteId($targetSite->id)->status(null)->one();
            self::assertInstanceOf(ShortLink::class, $siteLink);

            $redirectUrl = $siteLink->getUrl();
            $imageUrl = $siteLink->getQrCodeUrl();
            $displayUrl = $siteLink->getQrCodeDisplayUrl();
            self::assertStringContainsString('/' . $case['identifier'] . '/', $redirectUrl);
            self::assertStringContainsString('/' . $case['identifier'] . '/', $imageUrl);
            self::assertStringContainsString('/' . $case['identifier'] . '/', $displayUrl);

            $redirect = $this->request($redirectUrl);
            self::assertSame(
                302,
                $redirect['status'],
                $redirect['body'] . "\n" . $this->serverOutput() . "\n" . $this->fixtureLogOutput($projectRoot),
            );
            self::assertSame('https://destination.example/secondary-site', $redirect['location']);

            $go = $this->request(
                $origin . '/' . $case['identifier'] . '/shortlink-manager/redirect/go/' . $siteLink->slug,
            );
            self::assertSame(302, $go['status']);
            self::assertSame('https://destination.example/secondary-site', $go['location']);

            $image = $this->request($imageUrl);
            self::assertSame(
                200,
                $image['status'],
                $image['body'] . "\n" . $this->serverOutput() . "\n" . $this->fixtureLogOutput($projectRoot),
            );
            self::assertSame('image/png', $image['contentType']);
            self::assertSame('public, max-age=86400', $image['cacheControl']);
            self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $image['body']);

            $display = $this->request($displayUrl);
            self::assertSame(
                200,
                $display['status'],
                $display['body'] . "\n" . $this->serverOutput() . "\n" . $this->fixtureLogOutput($projectRoot),
            );
            self::assertStringContainsString('<html lang="' . $targetSite->language . '">', $display['body']);
            self::assertStringContainsString('data-site-id="' . $targetSite->id . '"', $display['body']);
            self::assertStringContainsString('data-template="site-qr"', $display['body']);
            self::assertStringContainsString($imageUrl, $display['body']);

            $evidence[$label] = [
                'identifier' => $case['identifier'],
                'redirect' => ['url' => $redirectUrl, 'status' => $redirect['status'], 'location' => $redirect['location']],
                'go' => ['status' => $go['status'], 'location' => $go['location']],
                'qrImage' => ['url' => $imageUrl, 'status' => $image['status'], 'sha256' => hash('sha256', $image['body'])],
                'qrDisplay' => ['url' => $displayUrl, 'status' => $display['status'], 'language' => $targetSite->language],
            ];
        }

        self::assertCount(3, array_unique([
            $evidence['handle']['qrImage']['sha256'],
            $evidence['id']['qrImage']['sha256'],
            $evidence['uid']['qrImage']['sha256'],
        ]), 'Each QR must encode its token-specific generated redirect URL.');

        foreach (['999999999', '00000000-0000-4000-8000-000000000000'] as $unknown) {
            self::assertSame(404, $this->request("{$origin}/{$unknown}/s/{$link->slug}")['status']);
            self::assertSame(404, $this->request("{$origin}/{$unknown}/s/qr/{$link->slug}")['status']);
        }

        $this->restartServerWithConfig($projectRoot, $port, $origin . '/{siteHandle}');
        $link->directRedirect = false;
        $link->passQueryParams = true;
        $link->trackAnalytics = true;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        $this->setDestinationForSite(
            $link,
            $targetSite,
            'https://destination.example/tracked?existing=destination&kept=1#details',
        );
        $hitsBeforeTrackedHop = $this->fetchHitsFromDb((int)$link->id);
        $analyticsBeforeTrackedHop = $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]);
        $landingQuery = http_build_query([
            'existing' => 'visitor',
            'campaign' => 'summer',
            'code' => 'visitor-code',
            'site' => 'visitor-site',
            '__sl_query' => 'visitor-namespace-value',
            'filters' => ['status' => ['new', 'active']],
            'src' => 'qr',
            'debug' => '1',
        ]);
        $trackedLanding = $this->request("{$origin}/{$targetSite->handle}/s/{$link->slug}?{$landingQuery}");
        self::assertSame(200, $trackedLanding['status'], $trackedLanding['body']);
        self::assertSame($hitsBeforeTrackedHop, $this->fetchHitsFromDb((int)$link->id));
        self::assertSame(
            $analyticsBeforeTrackedHop,
            $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]),
        );
        self::assertMatchesRegularExpression('/data-go-url href="([^"]+)"/', $trackedLanding['body']);
        preg_match('/data-go-url href="([^"]+)"/', $trackedLanding['body'], $goMatch);
        $trackedGoUrl = html_entity_decode($goMatch[1], ENT_QUOTES | ENT_HTML5);
        $trackedRedirect = $this->request($trackedGoUrl);
        self::assertSame(302, $trackedRedirect['status'], $trackedRedirect['body']);
        self::assertNotNull($trackedRedirect['location']);
        $trackedDestinationQuery = [];
        parse_str((string)parse_url($trackedRedirect['location'], PHP_URL_QUERY), $trackedDestinationQuery);
        self::assertSame([
            'existing' => 'visitor',
            'kept' => '1',
            'campaign' => 'summer',
            'code' => 'visitor-code',
            'site' => 'visitor-site',
            '__sl_query' => 'visitor-namespace-value',
            'filters' => ['status' => ['new', 'active']],
        ], $trackedDestinationQuery);
        self::assertSame('details', parse_url($trackedRedirect['location'], PHP_URL_FRAGMENT));
        self::assertSame($hitsBeforeTrackedHop + 1, $this->fetchHitsFromDb((int)$link->id));
        self::assertSame(
            $analyticsBeforeTrackedHop + 1,
            $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]),
        );
        $evidence['trackedTwoHop'] = [
            'landingStatus' => $trackedLanding['status'],
            'goStatus' => $trackedRedirect['status'],
            'location' => $trackedRedirect['location'],
            'hitDelta' => 1,
            'analyticsDelta' => 1,
        ];

        $this->restartServerWithConfig($projectRoot, $port, $origin . '/{siteHandle}');
        $link->directRedirect = false;
        $link->dateExpired = null;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));

        $siteRedirect = $this->request("{$origin}/{$targetSite->handle}/s/{$link->slug}");
        self::assertSame(200, $siteRedirect['status'], $siteRedirect['body']);
        self::assertStringContainsString('site-redirect', $siteRedirect['body']);

        $primaryVariant = ShortLink::find()->id($link->id)->siteId($primarySite->id)->status(null)->one();
        self::assertInstanceOf(ShortLink::class, $primaryVariant);
        $primaryVariant->directRedirect = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($primaryVariant));
        $globalRedirect = $this->request("{$origin}/{$primarySite->handle}/s/{$link->slug}");
        self::assertSame(200, $globalRedirect['status'], $globalRedirect['body']);
        self::assertStringContainsString('global-redirect', $globalRedirect['body']);

        $link->dateExpired = new \DateTime('-1 hour');
        $link->expiredRedirectUrl = null;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        $expired = $this->request("{$origin}/{$targetSite->handle}/s/{$link->slug}");
        self::assertSame(200, $expired['status'], $expired['body']);
        self::assertStringContainsString('site-expired', $expired['body']);

        $link->dateExpired = null;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        $missingTemplates = $rawTemplates;
        $missingTemplates['redirectTemplate'] = '$SHORTLINK_MANAGER_HTTP_MISSING_TEMPLATE';
        self::assertSame(302, $this->saveTemplateSettings($origin, $missingTemplates)['status']);
        $missingRuntime = $this->request("{$origin}/{$targetSite->handle}/s/{$link->slug}");
        self::assertSame(500, $missingRuntime['status']);
        self::assertStringNotContainsString('global-redirect', $missingRuntime['body']);
        $missingSetup = $this->request(
            $origin . '/admin/shortlink-manager/setup',
            cookieJar: $this->cookieJarPath,
        );
        self::assertSame(200, $missingSetup['status']);
        self::assertStringContainsString('Starter template is missing.', $missingSetup['body']);

        $link->dateExpired = null;
        $link->directRedirect = true;
        $link->passQueryParams = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        $this->setDestinationForSite($link, $targetSite, 'https://destination.example/graphql-secondary');
        $this->restartServerWithConfig($projectRoot, $port, $origin . '/{siteHandle}');
        $graphqlEvidence = $this->runGraphqlHttpSmoke(
            $projectRoot,
            $origin,
            $link,
            $primarySite,
            $targetSite,
        );

        $evidence['configuredTemplates'] = [
            'rawExpressionsVisible' => true,
            'setupResolvedTemplatesReady' => true,
            'authenticatedCacheControl' => $general['cacheControl'],
            'siteRedirect' => ['status' => $siteRedirect['status'], 'marker' => 'site-redirect'],
            'globalRedirect' => ['status' => $globalRedirect['status'], 'marker' => 'global-redirect'],
            'expired' => ['status' => $expired['status'], 'marker' => 'site-expired'],
            'qrDisplay' => ['status' => $evidence['handle']['qrDisplay']['status'], 'marker' => 'site-qr'],
            'missing' => ['runtimeStatus' => $missingRuntime['status'], 'setupMissing' => true],
        ];
        $evidence['graphqlExactSite'] = $graphqlEvidence;

        $evidencePath = $_SERVER['SHORTLINK_MANAGER_HTTP_SMOKE_EVIDENCE'] ?? null;
        if (!is_string($evidencePath) || $evidencePath !== $projectRoot . '/http-smoke.json') {
            self::fail('HTTP smoke evidence path must be owned by the disposable project.');
        }
        self::assertNotFalse(file_put_contents(
            $evidencePath,
            json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
        ));
    }

    private function disposableProjectRoot(): string
    {
        $projectRoot = $_SERVER['SHORTLINK_MANAGER_TEST_PROJECT_ROOT'] ?? null;
        $expected = '#^' . preg_quote(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR), '#')
            . '/shortlink-manager-fixture-[a-f0-9]{16}$#';
        if (!is_string($projectRoot) || preg_match($expected, $projectRoot) !== 1) {
            self::markTestSkipped('The HTTP smoke runs only inside the owned disposable Craft project.');
        }

        return $projectRoot;
    }

    private function secondarySite(): Site
    {
        $primaryId = Craft::$app->getSites()->getPrimarySite()->id;
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            if ($site->id !== $primaryId) {
                return $site;
            }
        }

        self::fail('The HTTP smoke requires at least two owned fixture sites.');
    }

    private function setDestinationForSite(ShortLink $link, Site $site, string $destinationUrl): void
    {
        $variant = ShortLink::find()->id($link->id)->siteId($site->id)->status(null)->one();
        self::assertInstanceOf(ShortLink::class, $variant);
        $variant->destinationUrl = $destinationUrl;
        self::assertTrue(Craft::$app->getElements()->saveElement($variant));
    }

    private function writeConfig(
        string $projectRoot,
        string $baseUrl,
    ): void {
        $this->configPath = $projectRoot . '/config/shortlink-manager.php';
        $this->templatePath = $projectRoot . '/templates/shortlink-http-qr.twig';
        self::assertNotFalse(file_put_contents(
            $this->templatePath,
            '<!doctype html><html lang="{{ currentSite.language }}"><body>'
            . '<span data-site-id="{{ currentSite.id }}">{{ siteName }}</span>'
            . '<img src="{{ shortLink.getQrCodeUrl() }}"></body></html>',
        ));
        $config = [
            'shortlinkBaseUrl' => $baseUrl,
            'usePrefix' => true,
            'slugPrefix' => 's',
            'qrPrefix' => 's/qr',
            'directRedirect' => true,
            'enableAnalytics' => true,
            'enabledIntegrations' => [],
        ];
        self::assertNotFalse(file_put_contents(
            $this->configPath,
            "<?php\nreturn " . var_export($config, true) . ";\n",
        ));
        DateFormatHelper::clearConfigCache('shortlink-manager');
    }

    private function writeRuntimeTemplates(string $projectRoot, Site $targetSite): void
    {
        $globalDirectory = $projectRoot . '/templates/shortlink-http';
        $siteDirectory = $projectRoot . '/templates/' . $targetSite->handle . '/shortlink-http';
        foreach ([$globalDirectory, $siteDirectory] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                self::fail("Unable to create HTTP template directory: {$directory}");
            }
        }

        $templates = [
            $globalDirectory . '/redirect.twig' => '<!doctype html><html><body>global-redirect'
                . '<a data-go-url href="{{ goUrl }}">Continue</a></body></html>',
            $globalDirectory . '/expired.twig' => '<!doctype html><html><body>global-expired</body></html>',
            $globalDirectory . '/qr.twig' => '<!doctype html><html lang="{{ currentSite.language }}"><body data-template="global-qr">'
                . '<span data-site-id="{{ currentSite.id }}">{{ siteName }}</span>'
                . '<img src="{{ shortLink.getQrCodeUrl() }}"></body></html>',
            $siteDirectory . '/redirect.twig' => '<!doctype html><html><body>site-redirect'
                . '<a data-go-url href="{{ goUrl }}">Continue</a></body></html>',
            $siteDirectory . '/expired.twig' => '<!doctype html><html><body>site-expired</body></html>',
            $siteDirectory . '/qr.twig' => '<!doctype html><html lang="{{ currentSite.language }}"><body data-template="site-qr">'
                . '<span data-site-id="{{ currentSite.id }}">{{ siteName }}</span>'
                . '<img src="{{ shortLink.getQrCodeUrl() }}"></body></html>',
        ];
        foreach ($templates as $path => $contents) {
            self::assertNotFalse(file_put_contents($path, $contents));
            $this->runtimeTemplatePaths[] = $path;
        }
    }

    /**
     * @param array{redirectTemplate: string, expiredTemplate: string, qrTemplate: string} $templates
     * @return array{status: int, location: ?string, contentType: ?string, cacheControl: ?string, body: string}
     */
    private function saveTemplateSettings(string $origin, array $templates): array
    {
        self::assertNotNull($this->cookieJarPath);

        return $this->request(
            $origin . '/index.php?p=actions/shortlink-manager/settings/save',
            post: [
                'section' => 'general',
                'settings' => $templates,
            ],
            cookieJar: $this->cookieJarPath,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function runGraphqlHttpSmoke(
        string $projectRoot,
        string $origin,
        ShortLink $link,
        Site $siteWithoutMatch,
        Site $matchedSite,
    ): array {
        $marker = 'shortlink-http-graphql-' . bin2hex(random_bytes(6));
        $schema = new GqlSchema([
            'name' => $marker,
            'scope' => [
                'shortlinkManager.all:read',
                "sites.{$siteWithoutMatch->uid}:read",
                "sites.{$matchedSite->uid}:read",
            ],
        ]);
        self::assertTrue(Craft::$app->getGql()->saveSchema($schema));
        self::assertNotNull($schema->id);
        $accessToken = bin2hex(random_bytes(24));
        $token = new GqlToken([
            'name' => $marker,
            'accessToken' => $accessToken,
            'schemaId' => $schema->id,
            'enabled' => true,
        ]);
        self::assertTrue(Craft::$app->getGql()->saveToken($token));
        self::assertNotNull($token->id);

        $this->writeGraphqlScopeState($link, $siteWithoutMatch, $matchedSite, true);

        $query = <<<'GRAPHQL'
query ResolveShortlink($code: String!, $site: String!) {
  shortlinkManagerResolveShortlink(code: $code, site: $site) {
    id
    siteId
    resolvedDestinationUrl
    hits
  }
}
GRAPHQL;
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ];
        $hitsBefore = $this->fetchHitsFromDb((int)$link->id);
        $analyticsBefore = $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]);

        try {
            $missing = $this->request(
                $origin . '/index.php?p=actions/graphql/api',
                rawBody: json_encode([
                    'query' => $query,
                    'variables' => ['code' => $link->slug, 'site' => $siteWithoutMatch->handle],
                ], JSON_THROW_ON_ERROR),
                headers: $headers,
            );
            self::assertSame(200, $missing['status'], $missing['body'] . "\n" . $this->fixtureLogOutput($projectRoot));
            $missingPayload = json_decode($missing['body'], true, flags: JSON_THROW_ON_ERROR);
            self::assertNull($missingPayload['data']['shortlinkManagerResolveShortlink'] ?? null, $missing['body']);
            self::assertSame($hitsBefore, $this->fetchHitsFromDb((int)$link->id));
            self::assertSame(
                $analyticsBefore,
                $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]),
            );

            $matched = $this->request(
                $origin . '/index.php?p=actions/graphql/api',
                rawBody: json_encode([
                    'query' => $query,
                    'variables' => ['code' => $link->slug, 'site' => $matchedSite->handle],
                ], JSON_THROW_ON_ERROR),
                headers: $headers,
            );
            self::assertSame(200, $matched['status'], $matched['body'] . "\n" . $this->fixtureLogOutput($projectRoot));
            $matchedPayload = json_decode($matched['body'], true, flags: JSON_THROW_ON_ERROR);
            $resolved = $matchedPayload['data']['shortlinkManagerResolveShortlink'] ?? null;
            self::assertIsArray($resolved, $matched['body']);
            self::assertSame($matchedSite->id, (int)$resolved['siteId']);
            self::assertSame('https://destination.example/graphql-secondary', $resolved['resolvedDestinationUrl']);
            self::assertSame($hitsBefore + 1, $this->fetchHitsFromDb((int)$link->id));
            self::assertSame(
                $analyticsBefore + 1,
                $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]),
            );

            return [
                'missingSiteStatus' => $missing['status'],
                'missingSiteResult' => null,
                'missingSiteHitDelta' => 0,
                'missingSiteAnalyticsDelta' => 0,
                'matchedSiteStatus' => $matched['status'],
                'matchedSiteId' => (int)$resolved['siteId'],
                'matchedSiteHitDelta' => 1,
                'matchedSiteAnalyticsDelta' => 1,
            ];
        } finally {
            $this->writeGraphqlScopeState($link, $siteWithoutMatch, $matchedSite, false);
            self::assertTrue(Craft::$app->getGql()->deleteTokenById((int)$token->id));
            self::assertTrue(Craft::$app->getGql()->deleteSchemaById((int)$schema->id));
            self::assertSame(0, $this->countRows('{{%gqltokens}}', ['name' => $marker]));
            self::assertSame(0, $this->countRows('{{%gqlschemas}}', ['name' => $marker]));
        }
    }

    private function prepareGraphqlServiceConfig(
        string $projectRoot,
        ShortLink $link,
        Site $siteWithoutMatch,
        Site $matchedSite,
    ): void {
        $this->graphqlScopePath = $projectRoot . '/shortlink-http-graphql-scope.json';
        $this->writeGraphqlScopeState($link, $siteWithoutMatch, $matchedSite, false);

        $appConfigPath = $projectRoot . '/config/app.php';
        $appConfig = require $appConfigPath;
        self::assertIsArray($appConfig);
        $scopePath = var_export($this->graphqlScopePath, true);
        self::assertNotFalse(file_put_contents(
            $appConfigPath,
            "<?php\n\$config = " . var_export($appConfig, true) . ";\n"
            . "\$config['on beforeRequest'] = static function(): void {\n"
            . "    \$plugin = Craft::\$app->getPlugins()->getPlugin('shortlink-manager');\n"
            . "    \$plugin->set('shortLinks', new lindemannrock\\shortlinkmanager\\tests\\Fixtures\\Http\\HttpGraphqlScopedShortLinksService({$scopePath}));\n"
            . "};\n"
            . "return \$config;\n",
        ));
    }

    private function writeGraphqlScopeState(
        ShortLink $link,
        Site $siteWithoutMatch,
        Site $matchedSite,
        bool $enabled,
    ): void {
        self::assertNotNull($this->graphqlScopePath);
        self::assertNotFalse(file_put_contents($this->graphqlScopePath, json_encode([
            'enabled' => $enabled,
            'linkId' => $link->id,
            'code' => $link->slug,
            'siteWithoutMatch' => $siteWithoutMatch->id,
            'matchedSiteId' => $matchedSite->id,
        ], JSON_THROW_ON_ERROR)));
    }

    private function availablePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (!is_resource($socket)) {
            self::fail("Unable to reserve HTTP smoke port: {$errorCode} {$errorMessage}");
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if (!is_string($address) || preg_match('/:(\d+)$/', $address, $matches) !== 1) {
            self::fail('Unable to resolve the HTTP smoke port.');
        }

        return (int)$matches[1];
    }

    private function startServer(string $projectRoot, int $port): void
    {
        $environment = [];
        foreach ($_SERVER as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $environment[$name] = $value;
            }
        }
        $router = dirname(__DIR__) . '/Fixtures/Http/router.php';
        $this->serverProcess = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", $router],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $this->serverPipes,
            $projectRoot . '/web',
            $environment,
        );
        if (!is_resource($this->serverProcess)) {
            self::fail('Unable to start the owned HTTP smoke server.');
        }
        fclose($this->serverPipes[0]);
        stream_set_blocking($this->serverPipes[1], false);
        stream_set_blocking($this->serverPipes[2], false);

        $deadline = microtime(true) + 15.0;
        do {
            usleep(100000);
            $probe = $this->request("http://127.0.0.1:{$port}/__shortlink-smoke-ready__", false);
            if ($probe !== null) {
                return;
            }
        } while (microtime(true) < $deadline);

        self::fail('Owned HTTP smoke server did not become ready: ' . $this->serverOutput());
    }

    private function restartServerWithConfig(string $projectRoot, int $port, string $baseUrl): void
    {
        $this->stopServer();
        $this->writeConfig($projectRoot, $baseUrl);
        $this->startServer($projectRoot, $port);
    }

    private function stopServer(): void
    {
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess, SIGTERM);
            proc_close($this->serverProcess);
        }
        $this->serverProcess = null;
        foreach ($this->serverPipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $this->serverPipes = [];
    }

    /**
     * @return array{status: int, location: ?string, contentType: ?string, cacheControl: ?string, body: string}|null
     */
    private function request(
        string $url,
        bool $failOnConnection = true,
        ?array $post = null,
        ?string $cookieJar = null,
        ?string $rawBody = null,
        array $headers = [],
    ): ?array {
        $curl = curl_init($url);
        if ($curl === false) {
            self::fail('Unable to initialize the HTTP smoke client.');
        }
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'LindemannRock ShortLink Disposable HTTP Smoke',
        ]);
        if ($post !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        if ($rawBody !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $rawBody);
        }
        if ($headers !== []) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        if ($cookieJar !== null) {
            curl_setopt($curl, CURLOPT_COOKIEJAR, $cookieJar);
            curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieJar);
        }
        $raw = curl_exec($curl);
        if (!is_string($raw)) {
            $error = curl_error($curl);
            curl_close($curl);
            if (!$failOnConnection) {
                return null;
            }
            self::fail("HTTP smoke request failed for {$url}: {$error}; server: " . $this->serverOutput());
        }
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        curl_close($curl);

        return [
            'status' => $status,
            'location' => $this->headerValue($headers, 'Location'),
            'contentType' => $this->headerValue($headers, 'Content-Type'),
            'cacheControl' => $this->headerValue($headers, 'Cache-Control'),
            'body' => $body,
        ];
    }

    private function headerValue(string $headers, string $name): ?string
    {
        if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/mi', $headers, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private function serverOutput(): string
    {
        $output = '';
        foreach ([1, 2] as $index) {
            if (isset($this->serverPipes[$index]) && is_resource($this->serverPipes[$index])) {
                $output .= stream_get_contents($this->serverPipes[$index]);
            }
        }

        return trim($output);
    }

    private function fixtureLogOutput(string $projectRoot): string
    {
        $output = '';
        foreach (glob($projectRoot . '/storage/logs/*.log') ?: [] as $path) {
            $contents = file_get_contents($path);
            if (is_string($contents)) {
                $output .= "\n{$path}:\n" . substr($contents, -12000);
            }
        }

        return trim($output);
    }
}
