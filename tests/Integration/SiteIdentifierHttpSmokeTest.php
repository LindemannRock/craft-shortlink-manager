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
            DateFormatHelper::clearConfigCache('shortlink-manager');
        } finally {
            parent::tearDown();
        }
    }

    public function testGeneratedHandleIdAndUidUrlsReachExactSiteOverHttp(): void
    {
        $projectRoot = $this->disposableProjectRoot();
        $targetSite = $this->secondarySite();
        $link = $this->seedShortLink([
            'siteId' => $targetSite->id,
            'destinationUrl' => 'https://destination.example/secondary-site',
        ]);
        $link->directRedirect = true;
        $link->passQueryParams = false;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        $this->setDestinationForSite($link, Craft::$app->getSites()->getPrimarySite(), 'https://destination.example/primary-site');

        $port = $this->availablePort();
        $origin = "http://127.0.0.1:{$port}";
        $this->writeConfig($projectRoot, $origin . '/{siteHandle}');
        $this->startServer($projectRoot, $port);

        $evidence = [];
        foreach ([
            'handle' => ['token' => '{siteHandle}', 'identifier' => $targetSite->handle],
            'id' => ['token' => '{siteId}', 'identifier' => (string)$targetSite->id],
            'uid' => ['token' => '{siteUid}', 'identifier' => $targetSite->uid],
        ] as $label => $case) {
            $this->writeConfig($projectRoot, $origin . '/' . $case['token']);
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

    private function writeConfig(string $projectRoot, string $baseUrl): void
    {
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
            'enableAnalytics' => false,
            'enabledIntegrations' => [],
            'qrTemplate' => 'shortlink-http-qr',
        ];
        self::assertNotFalse(file_put_contents(
            $this->configPath,
            "<?php\nreturn " . var_export($config, true) . ";\n",
        ));
        DateFormatHelper::clearConfigCache('shortlink-manager');
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
    private function request(string $url, bool $failOnConnection = true): ?array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            self::fail('Unable to initialize the HTTP smoke client.');
        }
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
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
