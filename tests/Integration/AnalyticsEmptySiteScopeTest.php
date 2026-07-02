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
use craft\console\User as ConsoleUser;
use lindemannrock\shortlinkmanager\controllers\AnalyticsController;
use lindemannrock\shortlinkmanager\tests\TestCase;
use lindemannrock\shortlinkmanager\widgets\AnalyticsSummaryWidget;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Pins the analytics site-scope invariant: null means all sites, [] means no sites.
 *
 * @since 5.25.0
 */
#[CoversClass(AnalyticsController::class)]
#[CoversClass(AnalyticsSummaryWidget::class)]
final class AnalyticsEmptySiteScopeTest extends TestCase
{
    private mixed $originalUser = null;

    protected function tearDown(): void
    {
        if ($this->originalUser !== null) {
            Craft::$app->set('user', $this->originalUser);
            $this->resetEditableSiteIds();
        }

        parent::tearDown();
    }

    public function testServiceLayerTreatsEmptySiteArrayAsNoRows(): void
    {
        $sites = Craft::$app->getSites()->getAllSites();
        if (count($sites) < 2) {
            self::markTestSkipped('Empty site-scope regression requires at least two Craft sites.');
        }

        $siteA = $sites[0];
        $siteB = $sites[1];

        $this->withSettings(['enabledSites' => [(int) $siteA->id, (int) $siteB->id]], function() use ($siteA, $siteB): void {
            $linkId = (new \craft\db\Query())
                ->from('{{%shortlinkmanager}}')
                ->select(['id'])
                ->scalar();
            if ($linkId === false) {
                self::markTestSkipped('Empty site-scope regression requires an existing shortlink row.');
            }

            $ipPrefix = '198.51.100.' . random_int(1, 200);
            $allBefore = $this->analytics->getAnalyticsSummary('last7days', null, [(int) $siteA->id, (int) $siteB->id]);

            try {
                foreach ([$siteA, $siteB] as $index => $site) {
                    Craft::$app->getDb()->createCommand()->insert('{{%shortlinkmanager_analytics}}', [
                        'linkId' => (int) $linkId,
                        'siteId' => (int) $site->id,
                        'deviceType' => 'desktop',
                        'trafficType' => 'human',
                        'ip' => $ipPrefix . $index,
                        'userAgent' => 'AnalyticsEmptySiteScopeTest',
                        'metadata' => '{}',
                        'dateCreated' => (new \DateTime())->format('Y-m-d H:i:s'),
                        'dateUpdated' => (new \DateTime())->format('Y-m-d H:i:s'),
                        'uid' => \craft\helpers\StringHelper::UUID(),
                    ])->execute();
                }

                $allSites = $this->analytics->getAnalyticsSummary('last7days', null, [(int) $siteA->id, (int) $siteB->id]);
                self::assertSame(2, $allSites['totalClicks'] - $allBefore['totalClicks']);

                $emptyScope = $this->analytics->getAnalyticsSummary('last7days', null, []);
                self::assertSame(0, $emptyScope['totalClicks']);
                self::assertSame(0, $emptyScope['uniqueVisitors']);
                self::assertSame(0, $emptyScope['activeLinks']);
                self::assertSame(0, $emptyScope['totalLinks']);
                self::assertSame(0, $emptyScope['linksUsed']);
                self::assertSame(0, $emptyScope['linksUsedPercentage']);
                self::assertSame([], $emptyScope['topLinks']);
            } finally {
                Craft::$app->getDb()->createCommand()
                    ->delete('{{%shortlinkmanager_analytics}}', ['like', 'ip', $ipPrefix, false])
                    ->execute();
            }
        });
    }

    public function testControllerResolvesNoEditableSitesToEmptyScope(): void
    {
        $this->withEditableSitePermissions([], function(): void {
            $controller = new AnalyticsController('analytics', \lindemannrock\shortlinkmanager\ShortLinkManager::getInstance());
            $method = new \ReflectionMethod($controller, '_resolveSiteId');
            $method->setAccessible(true);

            self::assertSame([], $method->invoke($controller, null));
        });
    }

    public function testWidgetEffectiveSiteIdReturnsEmptyScopeForNoEditableSites(): void
    {
        $this->withEditableSitePermissions([], function(): void {
            $widget = new EmptyScopeAnalyticsSummaryWidget();

            self::assertSame([], $widget->exposedEffectiveSiteId());
            self::assertStringContainsString('0', (string) $widget->getBodyHtml());
        });
    }

    public function testAnalyticsServicesUseNullOnlyForUnscopedSiteQueries(): void
    {
        $pluginRoot = dirname(__DIR__, 2);

        foreach ([
            '/src/services/AnalyticsService.php',
            '/src/services/analytics/AnalyticsBreakdownService.php',
            '/src/services/analytics/AnalyticsExportService.php',
            '/src/services/analytics/AnalyticsQueryInsightsService.php',
        ] as $path) {
            $source = file_get_contents($pluginRoot . $path);
            self::assertIsString($source);
            self::assertStringContainsString('$siteId !== null', $source);
            self::assertStringNotContainsString('if ($siteId) {', $source);
        }

        $summary = file_get_contents($pluginRoot . '/src/services/AnalyticsService.php');
        self::assertIsString($summary);
        self::assertStringContainsString('$siteId === []', $summary);
    }

    /**
     * @param string[] $siteUids
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withEditableSitePermissions(array $siteUids, callable $callback): mixed
    {
        if ($this->originalUser === null) {
            $this->originalUser = Craft::$app->getUser();
        }

        $permissions = array_map(
            static fn(string $siteUid): string => "editSite:{$siteUid}",
            $siteUids,
        );
        $permissions[] = 'shortLinkManager:viewAnalytics';

        Craft::$app->set('user', new EmptyScopeUser($permissions));
        $this->resetEditableSiteIds();

        try {
            return $callback();
        } finally {
            Craft::$app->set('user', $this->originalUser);
            $this->resetEditableSiteIds();
            $this->originalUser = null;
        }
    }

    private function resetEditableSiteIds(): void
    {
        $property = new \ReflectionProperty(Craft::$app->getSites(), '_editableSiteIds');
        $property->setAccessible(true);
        $property->setValue(Craft::$app->getSites(), null);
    }
}

final class EmptyScopeAnalyticsSummaryWidget extends AnalyticsSummaryWidget
{
    /**
     * @return int|array<int>
     */
    public function exposedEffectiveSiteId(): int|array
    {
        return $this->effectiveSiteId();
    }
}

final class EmptyScopeUser extends ConsoleUser
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
