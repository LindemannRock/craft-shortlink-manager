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

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedRequest = Craft::$app->getRequest();
        Craft::$app->set('request', new StubConsoleRequest(userIp: '203.0.113.42'));

        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());

        $this->applySettingsForTest([
            'ipHashSalt' => self::TEST_SALT,
            'enableAnalytics' => true,
            'enableGeoDetection' => false,
            'anonymizeIpAddress' => false,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->savedRequest !== null) {
            Craft::$app->set('request', $this->savedRequest);
        }

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

    public function testResolveQuerySanitizesExpiredRedirectUrl(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $link = $this->seedShortLink([
            'destinationUrl' => 'https://example.com/target',
            'siteId' => $site->id,
        ]);
        $this->forceStoredShortLinkValues((int)$link->id, $site->id, [
            'dateExpired' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
        ], [
            'expiredRedirectUrl' => 'javascript:alert(1)',
        ]);

        $result = ShortLinkResolver::resolve(
            null,
            [
                'code' => $link->slug,
                'siteId' => $site->id,
            ],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertIsArray($result);
        self::assertSame('/', $result['resolvedDestinationUrl']);
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

    public function testListQuerySanitizesResolvedDestinationUrl(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $link = $this->seedShortLink([
            'destinationUrl' => 'https://example.com/target',
            'siteId' => $site->id,
        ]);
        $this->forceStoredShortLinkValues((int)$link->id, $site->id, [], [
            'destinationUrl' => 'javascript:alert(1)',
        ]);

        $results = ShortLinkResolver::resolveAll(
            null,
            ['siteId' => $site->id],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertIsArray($results);
        $rowsById = [];
        foreach ($results as $row) {
            $rowsById[(int)$row['id']] = $row;
        }

        self::assertArrayHasKey((int)$link->id, $rowsById);
        self::assertSame('/', $rowsById[(int)$link->id]['resolvedDestinationUrl']);
    }

    public function testListQueryAppliesDefaultLimitWhenLimitIsOmitted(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();

        for ($i = 0; $i < 101; $i++) {
            $this->seedShortLink(['siteId' => $site->id]);
        }

        $results = ShortLinkResolver::resolveAll(
            null,
            ['siteId' => $site->id],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertCount(100, $results);
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

    /**
     * @param array<string, mixed> $elementValues
     * @param array<string, mixed> $contentValues
     */
    private function forceStoredShortLinkValues(int $linkId, int $siteId, array $elementValues, array $contentValues): void
    {
        if ($elementValues !== []) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%shortlinkmanager}}', $elementValues, ['id' => $linkId])
                ->execute();
        }

        if ($contentValues !== []) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%shortlinkmanager_content}}', $contentValues, [
                    'shortLinkId' => $linkId,
                    'siteId' => $siteId,
                ])
                ->execute();
        }

        $this->shortLinks->invalidateShortLinkCache($linkId);
    }
}
