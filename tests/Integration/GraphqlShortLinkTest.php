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
use craft\web\Request as WebRequest;
use GraphQL\Type\Definition\ResolveInfo;
use lindemannrock\base\testing\StubConsoleRequest;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\gql\queries\ShortLinkQuery;
use lindemannrock\shortlinkmanager\gql\resolvers\ShortLinkResolver;
use lindemannrock\shortlinkmanager\services\ShortLinksService;
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
        Craft::$app->set('request', new GraphqlWebRequest());

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
        $metadata = $this->decodeAnalyticsMetadata($analytics['metadata']);
        self::assertSame(['source' => 'graphql'], $metadata);
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
        Craft::$app->set('request', new GraphqlWebRequest());

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

    public function testExplicitSiteDoesNotResolveOrMutateAnotherSite(): void
    {
        $requestedSite = Craft::$app->getSites()->getPrimarySite();
        $matchedSite = $this->secondarySite();
        $link = $this->seedShortLink([
            'destinationUrl' => 'https://example.com/other-site',
            'siteId' => $matchedSite->id,
        ]);
        $this->swapPluginComponent(
            'shortlink-manager',
            'shortLinks',
            new GraphqlScopedShortLinksService($link, $requestedSite->id),
        );
        Craft::$app->set('request', new GraphqlWebRequest());

        $result = ShortLinkResolver::resolve(
            null,
            [
                'code' => $link->slug,
                'site' => $requestedSite->handle,
            ],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertNull($result);
        self::assertSame(0, $this->fetchHitsFromDb((int)$link->id));
        self::assertSame(0, $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]));
    }

    public function testExplicitHandleAndIdReturnOnlyTheRequestedVariant(): void
    {
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $secondarySite = $this->secondarySite();
        $link = $this->seedShortLink([
            'destinationUrl' => 'https://example.com/secondary',
            'siteId' => $secondarySite->id,
        ]);
        $this->setDestinationForSite($link, $primarySite->id, 'https://example.com/primary');
        Craft::$app->set('request', new GraphqlWebRequest());

        $primaryResult = $this->resolve([
            'code' => $link->slug,
            'site' => $primarySite->handle,
        ]);
        $secondaryResult = $this->resolve([
            'code' => $link->slug,
            'siteId' => $secondarySite->id,
        ]);

        self::assertIsArray($primaryResult);
        self::assertIsArray($secondaryResult);
        self::assertSame($primarySite->id, $primaryResult['siteId']);
        self::assertSame('https://example.com/primary', $primaryResult['resolvedDestinationUrl']);
        self::assertSame($secondarySite->id, $secondaryResult['siteId']);
        self::assertSame('https://example.com/secondary', $secondaryResult['resolvedDestinationUrl']);
        self::assertSame(2, $this->fetchHitsFromDb((int)$link->id));
        self::assertSame(2, $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]));
    }

    public function testOmittedSiteUsesOnlyTheCurrentSiteVariant(): void
    {
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $secondarySite = $this->secondarySite();
        $link = $this->seedShortLink([
            'destinationUrl' => 'https://example.com/secondary',
            'siteId' => $secondarySite->id,
        ]);
        $this->setDestinationForSite($link, $currentSite->id, 'https://example.com/current');
        Craft::$app->set('request', new GraphqlWebRequest());

        $result = $this->resolve(['code' => $link->slug]);

        self::assertIsArray($result);
        self::assertSame($currentSite->id, $result['siteId']);
        self::assertSame('https://example.com/current', $result['resolvedDestinationUrl']);
        self::assertSame(1, $this->fetchHitsFromDb((int)$link->id));
        self::assertSame(1, $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]));
    }

    public function testInvalidExplicitSitesDoNotResolveOrMutateLinks(): void
    {
        $link = $this->seedShortLink();
        Craft::$app->set('request', new GraphqlWebRequest());

        self::assertNull($this->resolve([
            'code' => $link->slug,
            'site' => '__missing_site__',
        ]));
        self::assertNull($this->resolve([
            'code' => $link->slug,
            'siteId' => 999999999,
        ]));
        self::assertSame(0, $this->fetchHitsFromDb((int)$link->id));
        self::assertSame(0, $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]));
    }

    public function testDisabledSiteDisabledLinkAndMissingDestinationAreRejected(): void
    {
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $secondarySite = $this->secondarySite();
        Craft::$app->set('request', new GraphqlWebRequest());

        $siteDisabledLink = $this->seedShortLink(['siteId' => $secondarySite->id]);
        $this->withSettings(['enabledSites' => [$primarySite->id]], function() use ($siteDisabledLink, $secondarySite): void {
            self::assertNull($this->resolve([
                'code' => $siteDisabledLink->slug,
                'siteId' => $secondarySite->id,
            ]));
        });

        $disabledLink = $this->seedShortLink(['siteId' => $primarySite->id]);
        $disabledLink->setEnabledForSite(false);
        self::assertTrue(Craft::$app->getElements()->saveElement($disabledLink));
        self::assertNull($this->resolve([
            'code' => $disabledLink->slug,
            'siteId' => $primarySite->id,
        ]));

        $missingDestination = $this->seedShortLink(['siteId' => $primarySite->id]);
        $missingDestination->destinationUrl = null;
        $missingDestination->elementId = null;
        $this->swapPluginComponent(
            'shortlink-manager',
            'shortLinks',
            new GraphqlScopedShortLinksService($missingDestination, -1),
        );
        self::assertNull($this->resolve([
            'code' => $missingDestination->slug,
            'siteId' => $primarySite->id,
        ]));

        foreach ([$siteDisabledLink, $disabledLink, $missingDestination] as $link) {
            self::assertSame(0, $this->fetchHitsFromDb((int)$link->id));
            self::assertSame(0, $this->countRows('{{%shortlinkmanager_analytics}}', ['linkId' => $link->id]));
        }
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

    private function secondarySite(): \craft\models\Site
    {
        $primarySiteId = Craft::$app->getSites()->getPrimarySite()->id;
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            if ($site->id !== $primarySiteId) {
                return $site;
            }
        }

        self::fail('GraphQL exact-site tests require at least two sites.');
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function resolve(array $arguments): mixed
    {
        return ShortLinkResolver::resolve(
            null,
            $arguments,
            null,
            $this->createMock(ResolveInfo::class),
        );
    }

    private function setDestinationForSite(ShortLink $link, int $siteId, string $destinationUrl): void
    {
        $variant = ShortLink::find()->id($link->id)->siteId($siteId)->status(null)->one();
        self::assertInstanceOf(ShortLink::class, $variant);
        $variant->destinationUrl = $destinationUrl;
        self::assertTrue(Craft::$app->getElements()->saveElement($variant));
    }
}

/**
 * Supplies an exact-site miss plus a different-site match for resolver contract tests.
 *
 * @since 5.29.0
 */
final class GraphqlScopedShortLinksService extends ShortLinksService
{
    public function __construct(
        private readonly ShortLink $matchedLink,
        private readonly int $siteWithoutMatch,
    ) {
        parent::__construct();
    }

    public function getByCode(string $code, ?int $siteId = null): ?ShortLink
    {
        if ($code !== $this->matchedLink->slug || $siteId === $this->siteWithoutMatch) {
            return null;
        }

        if ($siteId === null || $siteId === $this->matchedLink->siteId) {
            return $this->matchedLink;
        }

        return null;
    }
}

final class GraphqlWebRequest extends WebRequest
{
    public function getUserIP(int $filterOptions = 0): ?string
    {
        return '203.0.113.42';
    }

    public function getUserAgent(): ?string
    {
        return 'Mozilla/5.0 (Test) ShortLinkGraphqlFixture/1.0';
    }

    public function getReferrer(): ?string
    {
        return 'https://example.com/graphql';
    }
}
