<?php

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use craft\helpers\Json;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\base\testing\StubWebRequest;
use lindemannrock\shortlinkmanager\tests\Stubs\StubDeviceDetectionService;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins the contract for {@see \lindemannrock\shortlinkmanager\services\AnalyticsService::trackClick()}
 * (which delegates to {@see \lindemannrock\shortlinkmanager\services\analytics\AnalyticsTrackingService}).
 *
 * Covers:
 *  - happy path: a click writes one row keyed by linkId with the expected
 *    metadata, IP-hash, user-agent, and source payload
 *  - the `enableAnalytics = false` setting short-circuits the insert
 *  - the `ipHashSalt` (privacy-critical) genuinely controls the stored
 *    `ip` column — same IP + same salt → same hash, deterministic SHA-256
 *  - missing salt does NOT crash; row is still written but `ip` is null
 *    (the hashError path in `AnalyticsIpHelper::prepare()`)
 *  - `anonymizeIpAddress` masks the last IPv4 octet *before* hashing so
 *    `192.168.1.42` and `192.168.1.99` collapse to the same hash
 *
 * @since 5.19.0
 */
final class ClickTrackingTest extends TestCase
{
    private const TEST_SALT = '0123456789abcdef0123456789abcdef'; // 32-char fixed salt for determinism

    /** Track + restore settings mutations across tests. */
    private ?string $savedSalt = null;
    private bool $savedEnableAnalytics = true;
    private bool $savedAnonymize = false;
    private bool $savedEnableGeo = false;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Settings $settings */
        $settings = ShortLinkManager::$plugin->getSettings();
        $this->savedSalt = $settings->ipHashSalt;
        $this->savedEnableAnalytics = $settings->enableAnalytics;
        $this->savedAnonymize = $settings->anonymizeIpAddress;
        $this->savedEnableGeo = $settings->enableGeoDetection;

        $settings->ipHashSalt = self::TEST_SALT;
        $settings->enableAnalytics = true;
        $settings->anonymizeIpAddress = false;
        $settings->enableGeoDetection = false;

        // The real DeviceDetection chain calls `getQueryParam()` on the request,
        // which only exists on the web Request — the test bootstrap loads the
        // console Request. Swap to a deterministic stub for the duration of
        // each test; auto-restored by the base TestCase.
        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
    }

    protected function tearDown(): void
    {
        /** @var Settings $settings */
        $settings = ShortLinkManager::$plugin->getSettings();
        $settings->ipHashSalt = $this->savedSalt;
        $settings->enableAnalytics = $this->savedEnableAnalytics;
        $settings->anonymizeIpAddress = $this->savedAnonymize;
        $settings->enableGeoDetection = $this->savedEnableGeo;

        parent::tearDown();
    }

    public function testTrackClickWritesAnalyticsRowWithExpectedFields(): void
    {
        $link = $this->seedShortLink(['destinationUrl' => 'https://example.com/dest']);
        $request = new StubWebRequest(userIp: '203.0.113.42');

        $this->analytics->trackClick($link, $request, 'qr');

        $row = $this->fetchRow('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]);
        $this->assertNotNull($row, 'trackClick() should persist a single analytics row.');
        $this->assertSame($link->siteId, (int) $row['siteId']);
        $this->assertSame('https://example.com/dest', $row['destinationUrl']);
        $this->assertSame('Mozilla/5.0 (Test) LindemannRockStub/1.0', $row['userAgent']);
        $this->assertSame('https://example.com/some/page', $row['referrer']);
        if (array_key_exists('trafficType', $row)) {
            $this->assertSame('human', $row['trafficType']);
            $this->assertSame('0', (string)$row['isSystemAgent']);
            $this->assertSame('0', (string)$row['isRobot']);
            $this->assertNull($row['botCategory']);
            $this->assertNull($row['botProducerName']);
        }

        $exportRows = $this->analytics->getExportData($link->id, 'today', $link->siteId);
        $this->assertCount(1, $exportRows);
        $this->assertSame('No', $exportRows[0]['isRobot']);
        $this->assertArrayHasKey('browserEngine', $exportRows[0]);
        $this->assertArrayHasKey('language', $exportRows[0]);

        // Source is stored inside the metadata JSON blob.
        $this->assertNotEmpty($row['metadata']);
        $metadata = Json::decode($row['metadata']);
        $this->assertSame('qr', $metadata['source']);
    }

    public function testTrackClickHashesIpDeterministicallyWithSalt(): void
    {
        $link = $this->seedShortLink();
        $expectedHash = hash('sha256', '203.0.113.42' . self::TEST_SALT);

        $this->analytics->trackClick($link, new StubWebRequest(userIp: '203.0.113.42'));

        $row = $this->fetchRow('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]);
        $this->assertNotNull($row);
        $this->assertSame($expectedHash, $row['ip'], 'Stored IP must be sha256(ip+salt) — deterministic and salt-bound.');
        $this->assertSame(64, strlen($row['ip']), 'SHA-256 hex output is 64 chars.');
    }

    public function testTrackClickProducesSameHashForSameIpAcrossCalls(): void
    {
        $link = $this->seedShortLink();
        $request = new StubWebRequest(userIp: '203.0.113.42');

        $this->analytics->trackClick($link, $request);
        $this->analytics->trackClick($link, $request);

        $hashes = (new \craft\db\Query())
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $link->id])
            ->select(['ip'])
            ->column();

        $this->assertCount(2, $hashes, 'Two trackClick() calls should produce two analytics rows.');
        $this->assertSame($hashes[0], $hashes[1], 'Same IP + same salt → same hash. Repeat visitors are correlatable.');
    }

    public function testTrackClickAnonymizesIpv4BeforeHashing(): void
    {
        /** @var Settings $settings */
        $settings = ShortLinkManager::$plugin->getSettings();
        $settings->anonymizeIpAddress = true;

        $link = $this->seedShortLink();
        // Two IPs in the same /24 must collapse to the same anonymised form
        // (192.168.1.0), then hash to the same value.
        $this->analytics->trackClick($link, new StubWebRequest(userIp: '192.168.1.42'));
        $this->analytics->trackClick($link, new StubWebRequest(userIp: '192.168.1.99'));

        $hashes = (new \craft\db\Query())
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['linkId' => $link->id])
            ->select(['ip'])
            ->column();

        $this->assertCount(2, $hashes);
        $expected = hash('sha256', '192.168.1.0' . self::TEST_SALT);
        $this->assertSame($expected, $hashes[0]);
        $this->assertSame($expected, $hashes[1], 'IP anonymisation must run BEFORE hashing, so /24 neighbours share a hash.');
    }

    public function testTrackClickSkipsInsertWhenAnalyticsDisabled(): void
    {
        /** @var Settings $settings */
        $settings = ShortLinkManager::$plugin->getSettings();
        $settings->enableAnalytics = false;

        $link = $this->seedShortLink();
        $this->analytics->trackClick($link, new StubWebRequest());

        $this->assertSame(
            0,
            $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]),
            'trackClick must short-circuit when enableAnalytics is false.',
        );
    }

    public function testTrackClickWithUnconfiguredSaltStillWritesRowButNullsTheIp(): void
    {
        /** @var Settings $settings */
        $settings = ShortLinkManager::$plugin->getSettings();
        // Mirror the production "not configured" sentinel value defined in
        // AnalyticsTrackingService::hashIpWithSalt() — these two strings
        // both trigger the hash-error branch in AnalyticsIpHelper::prepare().
        $settings->ipHashSalt = '$SHORTLINK_MANAGER_IP_SALT';

        $link = $this->seedShortLink();
        $this->analytics->trackClick($link, new StubWebRequest(userIp: '203.0.113.42'));

        $row = $this->fetchRow('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]);
        $this->assertNotNull($row, 'A missing salt must not crash trackClick — the row should still land.');
        $this->assertNull($row['ip'], 'Without a salt, the IP must be persisted as null rather than an unsalted hash.');
    }
}
