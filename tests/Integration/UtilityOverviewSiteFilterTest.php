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
use craft\console\User as ConsoleUser;
use lindemannrock\base\testing\StubWebRequest;
use lindemannrock\shortlinkmanager\tests\Stubs\StubDeviceDetectionService;
use lindemannrock\shortlinkmanager\tests\TestCase;
use lindemannrock\shortlinkmanager\utilities\ShortLinkManagerUtility;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Pins the utility overview site filter contract.
 *
 * @since 5.25.0
 */
#[CoversClass(ShortLinkManagerUtility::class)]
final class UtilityOverviewSiteFilterTest extends TestCase
{
    private const TEST_SALT = '0123456789abcdef0123456789abcdef';

    private mixed $originalUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalUser = Craft::$app->getUser();
        Craft::$app->set('user', new UtilitySiteFilterUser($this->allEditSitePermissions()));
        $this->resetEditableSiteIds();
    }

    protected function tearDown(): void
    {
        if ($this->originalUser !== null) {
            Craft::$app->set('user', $this->originalUser);
            $this->resetEditableSiteIds();
        }

        parent::tearDown();
    }

    public function testMissingAndAllSiteParamUseAllEnabledSites(): void
    {
        $expectedSiteIds = $this->allSiteIds();

        $this->withSettings(['enabledSites' => $expectedSiteIds], function() use ($expectedSiteIds): void {
            $missing = $this->withSiteQuery([], fn(): array => $this->siteSelection());
            self::assertSame('all', $missing['selectedSiteHandle']);
            self::assertSame($expectedSiteIds, $missing['siteIds']);

            $all = $this->withSiteQuery(['site' => 'all'], fn(): array => $this->siteSelection());
            self::assertSame('all', $all['selectedSiteHandle']);
            self::assertSame($expectedSiteIds, $all['siteIds']);

            $empty = $this->withSiteQuery(['site' => ''], fn(): array => $this->siteSelection());
            self::assertSame('all', $empty['selectedSiteHandle']);
            self::assertSame($expectedSiteIds, $empty['siteIds']);
        });
    }

    public function testValidSiteHandleFiltersToThatEnabledSite(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();

        $this->withSettings(['enabledSites' => $this->allSiteIds()], function() use ($site): void {
            $selection = $this->withSiteQuery(['site' => $site->handle], fn(): array => $this->siteSelection());

            self::assertSame($site->handle, $selection['selectedSiteHandle']);
            self::assertSame($site->name, $selection['selectedSiteLabel']);
            self::assertSame([(int) $site->id], $selection['siteIds']);
        });
    }

    public function testInvalidAndDisabledSiteHandlesFallBackToAllSites(): void
    {
        $expectedSiteIds = $this->allSiteIds();

        $this->withSettings(['enabledSites' => $expectedSiteIds], function() use ($expectedSiteIds): void {
            $invalid = $this->withSiteQuery(['site' => 'not-a-real-site'], fn(): array => $this->siteSelection());
            self::assertSame('all', $invalid['selectedSiteHandle']);
            self::assertSame($expectedSiteIds, $invalid['siteIds']);
        });

        $sites = Craft::$app->getSites()->getAllSites();
        if (count($sites) < 2) {
            self::markTestSkipped('Disabled-site fallback requires at least two Craft sites.');
        }

        $enabledSite = $sites[0];
        $disabledSite = $sites[1];

        $this->withSettings(['enabledSites' => [(int) $enabledSite->id]], function() use ($enabledSite, $disabledSite): void {
            $selection = $this->withSiteQuery(['site' => $disabledSite->handle], fn(): array => $this->siteSelection());

            self::assertSame('all', $selection['selectedSiteHandle']);
            self::assertSame([(int) $enabledSite->id], $selection['siteIds']);
        });
    }

    public function testSiteRestrictedIdentityCannotSelectUneditableEnabledSite(): void
    {
        $sites = Craft::$app->getSites()->getAllSites();
        if (count($sites) < 2) {
            self::markTestSkipped('Editable-site fallback requires at least two Craft sites.');
        }

        $editableSite = $sites[0];
        $uneditableSite = $sites[1];

        $this->withSettings(['enabledSites' => [(int) $editableSite->id, (int) $uneditableSite->id]], function() use ($editableSite, $uneditableSite): void {
            $this->withEditableSitePermissions([$editableSite->uid], function() use ($editableSite, $uneditableSite): void {
                $allowed = $this->withSiteQuery(['site' => $editableSite->handle], fn(): array => $this->siteSelection());
                self::assertSame($editableSite->handle, $allowed['selectedSiteHandle']);
                self::assertSame([(int) $editableSite->id], $allowed['siteIds']);

                $blocked = $this->withSiteQuery(['site' => $uneditableSite->handle], fn(): array => $this->siteSelection());
                self::assertSame('all', $blocked['selectedSiteHandle']);
                self::assertSame([(int) $editableSite->id], $blocked['siteIds']);
                self::assertArrayNotHasKey($uneditableSite->handle, $blocked['siteOptions']);
            });
        });
    }

    public function testSelectedSiteIdsDriveOverviewLinkAndAnalyticsStats(): void
    {
        $sites = Craft::$app->getSites()->getAllSites();
        if (count($sites) < 2) {
            self::markTestSkipped('Site-specific utility stats require at least two Craft sites.');
        }

        $siteA = $sites[0];
        $siteB = $sites[1];

        $this->swapPluginComponent('shortlink-manager', 'deviceDetection', new StubDeviceDetectionService());
        $this->withSettings([
            'enabledSites' => [(int) $siteA->id, (int) $siteB->id],
            'enableAnalytics' => true,
            'ipHashSalt' => self::TEST_SALT,
            'enableGeoDetection' => false,
        ], function() use ($siteA, $siteB): void {
            $allSelection = $this->withSiteQuery([], fn(): array => $this->siteSelection());
            $siteASelection = $this->withSiteQuery(['site' => $siteA->handle], fn(): array => $this->siteSelection());
            $siteBSelection = $this->withSiteQuery(['site' => $siteB->handle], fn(): array => $this->siteSelection());

            $allLinksBefore = $this->linkStatusCounts($allSelection['siteIds']);
            $siteALinksBefore = $this->linkStatusCounts($siteASelection['siteIds']);
            $siteBLinksBefore = $this->linkStatusCounts($siteBSelection['siteIds']);
            $allAnalyticsBefore = $this->analyticsStats($allSelection['siteIds']);
            $siteAAnalyticsBefore = $this->analyticsStats($siteASelection['siteIds']);
            $siteBAnalyticsBefore = $this->analyticsStats($siteBSelection['siteIds']);

            $linkA = $this->seedShortLink(['siteId' => (int) $siteA->id]);
            $linkB = $this->seedShortLink(['siteId' => (int) $siteB->id]);
            $this->analytics->trackClick($linkA, new StubWebRequest(), 'direct');
            $this->analytics->trackClick($linkB, new StubWebRequest(), 'qr');

            $allLinksAfter = $this->linkStatusCounts($allSelection['siteIds']);
            $siteALinksAfter = $this->linkStatusCounts($siteASelection['siteIds']);
            $siteBLinksAfter = $this->linkStatusCounts($siteBSelection['siteIds']);
            $allAnalyticsAfter = $this->analyticsStats($allSelection['siteIds']);
            $siteAAnalyticsAfter = $this->analyticsStats($siteASelection['siteIds']);
            $siteBAnalyticsAfter = $this->analyticsStats($siteBSelection['siteIds']);

            self::assertSame(4, $allLinksAfter['totalLinks'] - $allLinksBefore['totalLinks']);
            self::assertSame(2, $siteALinksAfter['totalLinks'] - $siteALinksBefore['totalLinks']);
            self::assertSame(2, $siteBLinksAfter['totalLinks'] - $siteBLinksBefore['totalLinks']);

            self::assertSame(2, $allAnalyticsAfter['totalClicks'] - $allAnalyticsBefore['totalClicks']);
            self::assertSame(1, $allAnalyticsAfter['directClicks'] - $allAnalyticsBefore['directClicks']);
            self::assertSame(1, $allAnalyticsAfter['qrScans'] - $allAnalyticsBefore['qrScans']);
            self::assertSame(1, $siteAAnalyticsAfter['directClicks'] - $siteAAnalyticsBefore['directClicks']);
            self::assertSame(0, $siteAAnalyticsAfter['qrScans'] - $siteAAnalyticsBefore['qrScans']);
            self::assertSame(0, $siteBAnalyticsAfter['directClicks'] - $siteBAnalyticsBefore['directClicks']);
            self::assertSame(1, $siteBAnalyticsAfter['qrScans'] - $siteBAnalyticsBefore['qrScans']);
        });
    }

    public function testUtilityTemplateWiresHeaderSelectorAndInfoBox(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $template = file_get_contents($pluginRoot . '/src/templates/utilities/index.twig');

        self::assertIsString($template);
        self::assertStringContainsString('{% block headerActions %}', $template);
        self::assertStringContainsString('<select name="site"', $template);
        self::assertStringContainsString("'Select site'|t('shortlink-manager')", $template);
        self::assertStringContainsString('selectedSiteHandle == siteHandle', $template);
        self::assertStringContainsString('{% block beforeQuickActions %}', $template);
        self::assertStringContainsString("'Site'|t('shortlink-manager')", $template);
        self::assertStringContainsString('selectedSiteLabel', $template);
        self::assertStringContainsString("margin: 'both'", $template);
        self::assertStringContainsString('<br>', $template);

        $utility = file_get_contents($pluginRoot . '/src/utilities/ShortLinkManagerUtility.php');

        self::assertIsString($utility);
        self::assertStringContainsString("Craft::t('lindemannrock-base', 'All Sites')", $utility);
        self::assertStringContainsString('ShortLinkManager::$plugin->getEnabledSites()', $utility);
        self::assertStringNotContainsString("Craft::t('shortlink-manager', 'All Sites')", $utility);
        self::assertStringNotContainsString('$settings->getEnabledSiteIds()', $utility);
        self::assertStringContainsString("DbHelper::jsonExtract('metadata', 'source')", $utility);
        self::assertStringContainsString("'directClicks' => max(0, \$totalClicks - \$qrScans)", $utility);
        self::assertStringNotContainsString('sourceRowsQuery', $utility);
        self::assertStringNotContainsString('json_decode($click', $utility);
    }

    public function testCacheCountsAndServdPurgeRemainGlobal(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $utility = file_get_contents($pluginRoot . '/src/utilities/ShortLinkManagerUtility.php');
        $template = file_get_contents($pluginRoot . '/src/templates/utilities/index.twig');
        $servdService = file_get_contents($pluginRoot . '/src/services/ServdStaticCacheService.php');

        self::assertIsString($utility);
        self::assertIsString($template);
        self::assertIsString($servdService);

        $cacheBlock = substr($utility, strpos($utility, '// Get cache counts') ?: 0);
        self::assertStringContainsString("PluginHelper::getCachePath(ShortLinkManager::\$plugin, 'qr')", $cacheBlock);
        self::assertStringContainsString("PluginHelper::getCachePath(ShortLinkManager::\$plugin, 'device')", $cacheBlock);
        self::assertStringNotContainsString('$selectedSiteIds', $cacheBlock);

        self::assertStringContainsString('Queue a Servd purge for all public {linksName} URLs and QR landing pages.', $template);
        self::assertStringContainsString("actionUrl('shortlink-manager/settings/purge-servd-static-cache')", $template);
        self::assertStringContainsString('Purge Servd static cache for all {linksName}?', $template);
        self::assertStringNotContainsString("site: selectedSiteHandle", $template);

        self::assertStringContainsString('purgeAllUrls()', $servdService);
        self::assertStringNotContainsString('selectedSite', $servdService);
    }

    /**
     * @return list<int>
     */
    private function allSiteIds(): array
    {
        return array_map(
            static fn($site): int => (int) $site->id,
            Craft::$app->getSites()->getAllSites(),
        );
    }

    /**
     * @return string[]
     */
    private function allEditSitePermissions(): array
    {
        return array_map(
            static fn($site): string => "editSite:{$site->uid}",
            Craft::$app->getSites()->getAllSites(),
        );
    }

    /**
     * @param array<string, string> $queryParams
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withSiteQuery(array $queryParams, callable $callback): mixed
    {
        $request = Craft::$app->getRequest();
        Craft::$app->set('request', new UtilitySiteFilterRequest($queryParams));

        try {
            return $callback();
        } finally {
            Craft::$app->set('request', $request);
        }
    }

    /**
     * @param string[] $siteUids
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withEditableSitePermissions(array $siteUids, callable $callback): mixed
    {
        $user = Craft::$app->getUser();
        $permissions = array_map(
            static fn(string $siteUid): string => "editSite:{$siteUid}",
            $siteUids,
        );

        Craft::$app->set('user', new UtilitySiteFilterUser($permissions));
        $this->resetEditableSiteIds();

        try {
            return $callback();
        } finally {
            Craft::$app->set('user', $user);
            $this->resetEditableSiteIds();
        }
    }

    private function resetEditableSiteIds(): void
    {
        $property = new \ReflectionProperty(Craft::$app->getSites(), '_editableSiteIds');
        $property->setAccessible(true);
        $property->setValue(Craft::$app->getSites(), null);
    }

    /**
     * @return array{selectedSiteHandle: string, selectedSiteLabel: string, siteOptions: array<string, string>, siteIds: list<int>}
     */
    private function siteSelection(): array
    {
        return $this->invokeUtilityMethod('siteSelection');
    }

    /**
     * @param list<int> $siteIds
     * @return array{totalLinks: int, activeLinks: int, pendingLinks: int, expiredLinks: int, disabledLinks: int}
     */
    private function linkStatusCounts(array $siteIds): array
    {
        return $this->invokeUtilityMethod('linkStatusCounts', [$siteIds]);
    }

    /**
     * @param list<int> $siteIds
     * @return array{totalClicks: int, qrScans: int, directClicks: int}
     */
    private function analyticsStats(array $siteIds): array
    {
        return $this->invokeUtilityMethod('analyticsStats', [$siteIds]);
    }

    /**
     * @param list<mixed> $arguments
     * @return mixed
     */
    private function invokeUtilityMethod(string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod(ShortLinkManagerUtility::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $arguments);
    }
}

final class UtilitySiteFilterRequest extends ConsoleRequest
{
    /**
     * @param array<string, string> $queryParams
     */
    public function __construct(private readonly array $queryParams)
    {
        parent::__construct();
    }

    public function getQueryParam($name, $defaultValue = null): mixed
    {
        return $this->queryParams[$name] ?? $defaultValue;
    }

    public function getParam($name, $defaultValue = null): mixed
    {
        return $this->getQueryParam($name, $defaultValue);
    }
}

final class UtilitySiteFilterUser extends ConsoleUser
{
    /**
     * @param string[] $permissions
     */
    public function __construct(private readonly array $permissions)
    {
        parent::__construct();
    }

    public function checkPermission(string $permissionName): bool
    {
        return in_array(strtolower($permissionName), array_map('strtolower', $this->permissions), true);
    }

    public function getId(): ?int
    {
        return 1;
    }

    public function getIsGuest(): bool
    {
        return false;
    }
}
