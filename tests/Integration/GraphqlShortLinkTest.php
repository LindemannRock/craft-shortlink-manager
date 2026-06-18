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
use craft\helpers\Json;
use GraphQL\Type\Definition\ResolveInfo;
use lindemannrock\base\testing\StubConsoleRequest;
use lindemannrock\base\testing\StubWebRequest;
use lindemannrock\shortlinkmanager\gql\queries\ShortLinkQuery;
use lindemannrock\shortlinkmanager\gql\resolvers\ShortLinkResolver;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\Stubs\StubDeviceDetectionService;
use lindemannrock\shortlinkmanager\tests\TestCase;
use yii\base\Request as YiiRequest;

/**
 * Covers ShortLink Manager's GraphQL resolver contract.
 *
 * @since 5.21.0
 */
final class GraphqlShortLinkTest extends TestCase
{
    private const TEST_SALT = '0123456789abcdef0123456789abcdef';

    private ?YiiRequest $savedRequest = null;

    private ?string $savedSalt = null;

    private bool $savedEnableAnalytics = true;

    private bool $savedEnableGeo = false;

    private bool $savedAnonymize = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedRequest = Craft::$app->getRequest();
        Craft::$app->set('request', new StubConsoleRequest(userIp: '203.0.113.42'));

        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());

        /** @var Settings $settings */
        $settings = ShortLinkManager::$plugin->getSettings();
        $this->savedSalt = $settings->ipHashSalt;
        $this->savedEnableAnalytics = $settings->enableAnalytics;
        $this->savedEnableGeo = $settings->enableGeoDetection;
        $this->savedAnonymize = $settings->anonymizeIpAddress;

        $settings->ipHashSalt = self::TEST_SALT;
        $settings->enableAnalytics = true;
        $settings->enableGeoDetection = false;
        $settings->anonymizeIpAddress = false;
    }

    protected function tearDown(): void
    {
        if ($this->savedRequest !== null) {
            Craft::$app->set('request', $this->savedRequest);
        }

        /** @var Settings $settings */
        $settings = ShortLinkManager::$plugin->getSettings();
        $settings->ipHashSalt = $this->savedSalt;
        $settings->enableAnalytics = $this->savedEnableAnalytics;
        $settings->enableGeoDetection = $this->savedEnableGeo;
        $settings->anonymizeIpAddress = $this->savedAnonymize;

        parent::tearDown();
    }

    public function testQueryDefinitionsExposeResolveAndListQueriesWithoutTokenCheck(): void
    {
        $queries = ShortLinkQuery::getQueries(false);

        self::assertArrayHasKey('shortlinkManagerResolveShortlink', $queries);
        self::assertArrayHasKey('shortlinkManagerShortlinks', $queries);
        self::assertArrayHasKey('code', $queries['shortlinkManagerResolveShortlink']['args']);
        self::assertArrayHasKey('site', $queries['shortlinkManagerResolveShortlink']['args']);
        self::assertArrayHasKey('siteId', $queries['shortlinkManagerResolveShortlink']['args']);
        self::assertArrayHasKey('queryString', $queries['shortlinkManagerResolveShortlink']['args']);
    }

    public function testQueryDefinitionsAreSchemaPermissionGated(): void
    {
        self::assertSame([], ShortLinkQuery::getQueries());
    }

    public function testResolveQueryMatchesShortlinkAndRecordsAnalytics(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $link = $this->seedShortLink([
            'destinationUrl' => 'https://example.com/target',
            'siteId' => $site->id,
        ]);
        Craft::$app->set('request', new StubWebRequest(userIp: '203.0.113.42'));

        $result = ShortLinkResolver::resolve(
            null,
            [
                'code' => $link->slug,
                'site' => $site->handle,
            ],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertIsArray($result);
        self::assertSame($link->id, $result['id']);
        self::assertSame('https://example.com/target', $result['resolvedDestinationUrl']);
        self::assertSame(1, (int)$result['hits']);
        self::assertSame(1, $this->fetchHitsFromDb((int)$link->id));

        $analytics = $this->fetchRow('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]);
        self::assertNotNull($analytics, 'GraphQL resolution must record analytics.');
        self::assertSame($site->id, (int)$analytics['siteId']);
        self::assertSame('https://example.com/target', $analytics['destinationUrl']);
        self::assertNotEmpty($analytics['metadata']);
        $metadata = Json::decode($analytics['metadata']);
        self::assertSame('graphql', $metadata['source']);
    }

    public function testResolveQueryMergesQueryStringWhenEnabled(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $link = $this->seedShortLink([
            'destinationUrl' => 'https://example.com/target?existing=1',
            'siteId' => $site->id,
        ]);
        $link->passQueryParams = true;
        self::assertTrue(Craft::$app->getElements()->saveElement($link));
        Craft::$app->set('request', new StubWebRequest(userIp: '203.0.113.42'));

        $result = ShortLinkResolver::resolve(
            null,
            [
                'code' => $link->slug,
                'siteId' => $site->id,
                'queryString' => 'utm=test&existing=2&src=qr',
            ],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertIsArray($result);
        self::assertSame('https://example.com/target?existing=2&utm=test', $result['resolvedDestinationUrl']);
    }

    public function testListQueryIsReadOnly(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $link = $this->seedShortLink(['siteId' => $site->id]);

        $results = ShortLinkResolver::resolveAll(
            null,
            ['siteId' => $site->id],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertIsArray($results);
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $results);
        self::assertContains($link->id, $ids);
        self::assertSame(0, $this->fetchHitsFromDb((int)$link->id));
        self::assertSame(0, $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]));
    }

    public function testInvalidExplicitSiteDoesNotFallBack(): void
    {
        $this->seedShortLink();

        $result = ShortLinkResolver::resolveAll(
            null,
            ['site' => '__missing_site__'],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertSame([], $result);
    }
}
