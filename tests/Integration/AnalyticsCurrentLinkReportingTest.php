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
use craft\db\Query;
use craft\helpers\StringHelper;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins current-link membership for engagement and Top Links while preserving
 * historical analytics through normal element lifecycle transitions.
 *
 * @since 5.28.4
 */
final class AnalyticsCurrentLinkReportingTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $analyticsUids = [];

    protected function tearDown(): void
    {
        try {
            if ($this->analyticsUids !== []) {
                Craft::$app->getDb()->createCommand()
                    ->delete('{{%shortlinkmanager_analytics}}', ['uid' => $this->analyticsUids])
                    ->execute();
            }
        } finally {
            $this->analyticsUids = [];
            parent::tearDown();
        }
    }

    public function testCurrentReportsFollowDisableTrashRestoreAndPermanentDeleteLifecycle(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $siteId = (int)$site->id;
        $baseline = $this->analytics->getAnalyticsSummary('today', null, $siteId);
        $link = $this->seedShortLink(['siteId' => $siteId]);
        $linkId = (int)$link->id;
        $analyticsUid = $this->seedAnalyticsRow($linkId, $siteId);
        $actual = [];

        $actual['enabled'] = $this->currentReportingDelta($baseline, $siteId, $linkId, $analyticsUid);

        $link = $this->setLinkEnabledForSite($linkId, $siteId, false);
        $actual['disabled'] = $this->currentReportingDelta($baseline, $siteId, $linkId, $analyticsUid);

        $link = $this->setLinkEnabledForSite($linkId, $siteId, true);
        $actual['re-enabled'] = $this->currentReportingDelta($baseline, $siteId, $linkId, $analyticsUid);

        self::assertTrue(Craft::$app->getElements()->deleteElement($link));
        $actual['trashed'] = $this->currentReportingDelta($baseline, $siteId, $linkId, $analyticsUid);

        $trashedLink = ShortLink::find()
            ->id($linkId)
            ->siteId($siteId)
            ->status(null)
            ->trashed(true)
            ->one();
        self::assertInstanceOf(ShortLink::class, $trashedLink);
        self::assertTrue(Craft::$app->getElements()->restoreElement($trashedLink));
        $actual['restored'] = $this->currentReportingDelta($baseline, $siteId, $linkId, $analyticsUid);

        self::assertTrue(Craft::$app->getElements()->deleteElement($trashedLink, true));
        $actual['permanently-deleted'] = $this->currentReportingDelta($baseline, $siteId, $linkId, $analyticsUid);

        self::assertSame([
            'enabled' => [1, 1, 1, true, 1],
            'disabled' => [1, 0, 0, false, 1],
            're-enabled' => [1, 1, 1, true, 1],
            'trashed' => [1, 0, 0, false, 1],
            'restored' => [1, 1, 1, true, 1],
            'permanently-deleted' => [0, 0, 0, false, 0],
        ], $actual, 'Current reports must follow Craft lifecycle state without deleting historical analytics early.');
    }

    public function testCurrentReportsKeepEnabledSiteIsolatedFromDisabledSite(): void
    {
        $sites = Craft::$app->getSites()->getAllSites();
        if (count($sites) < 2) {
            self::markTestSkipped('Current-link site isolation requires at least two Craft sites.');
        }

        $siteA = $sites[0];
        $siteB = $sites[1];
        $siteAId = (int)$siteA->id;
        $siteBId = (int)$siteB->id;

        $this->withSettings(['enabledSites' => [$siteAId, $siteBId]], function() use ($siteAId, $siteBId): void {
            $baselineA = $this->analytics->getAnalyticsSummary('today', null, $siteAId);
            $baselineB = $this->analytics->getAnalyticsSummary('today', null, $siteBId);
            $link = $this->seedShortLink(['siteId' => $siteAId]);
            $linkId = (int)$link->id;

            $this->seedAnalyticsRow($linkId, $siteAId);
            $this->seedAnalyticsRow($linkId, $siteBId);
            $this->setLinkEnabledForSite($linkId, $siteBId, false);

            $this->assertCurrentReportingDelta($baselineA, $siteAId, $linkId, 1, 1, 1, true);
            $this->assertCurrentReportingDelta($baselineB, $siteBId, $linkId, 1, 0, 0, false);
        });
    }

    /**
     * @param array<string, mixed> $baseline
     */
    private function assertCurrentReportingDelta(
        array $baseline,
        int $siteId,
        int $linkId,
        int $clickDelta,
        int $activeDelta,
        int $engagedDelta,
        bool $isTopLink,
    ): void {
        $summary = $this->analytics->getAnalyticsSummary('today', null, $siteId);
        $topLinkIds = array_map(
            static fn(array $row): int => (int)$row['id'],
            $this->analytics->getTopLinks(100, 'today', $siteId),
        );

        self::assertSame((int)$baseline['totalClicks'] + $clickDelta, (int)$summary['totalClicks']);
        self::assertSame((int)$baseline['activeLinks'] + $activeDelta, (int)$summary['activeLinks']);
        self::assertSame((int)$baseline['linksUsed'] + $engagedDelta, (int)$summary['linksUsed']);
        self::assertSame($isTopLink, in_array($linkId, $topLinkIds, true));
    }

    /**
     * @param array<string, mixed> $baseline
     * @return array{int, int, int, bool, int}
     */
    private function currentReportingDelta(array $baseline, int $siteId, int $linkId, string $analyticsUid): array
    {
        $summary = $this->analytics->getAnalyticsSummary('today', null, $siteId);
        $topLinkIds = array_map(
            static fn(array $row): int => (int)$row['id'],
            $this->analytics->getTopLinks(100, 'today', $siteId),
        );

        return [
            (int)$summary['totalClicks'] - (int)$baseline['totalClicks'],
            (int)$summary['activeLinks'] - (int)$baseline['activeLinks'],
            (int)$summary['linksUsed'] - (int)$baseline['linksUsed'],
            in_array($linkId, $topLinkIds, true),
            $this->analyticsRowCount($analyticsUid),
        ];
    }

    private function setLinkEnabledForSite(int $linkId, int $siteId, bool $enabled): ShortLink
    {
        $link = ShortLink::find()
            ->id($linkId)
            ->siteId($siteId)
            ->status(null)
            ->trashed(null)
            ->one();
        self::assertInstanceOf(ShortLink::class, $link);

        $link->setEnabledForSite($enabled);
        self::assertTrue(
            Craft::$app->getElements()->saveElement($link),
            'The test-owned site variant must save through Craft.',
        );

        return $link;
    }

    private function seedAnalyticsRow(int $linkId, int $siteId): string
    {
        $uid = StringHelper::UUID();
        $now = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        Craft::$app->getDb()->createCommand()->insert('{{%shortlinkmanager_analytics}}', [
            'linkId' => $linkId,
            'siteId' => $siteId,
            'deviceType' => 'desktop',
            'trafficType' => 'human',
            'userAgent' => self::class,
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid' => $uid,
        ])->execute();
        $this->analyticsUids[] = $uid;

        return $uid;
    }

    private function analyticsRowCount(string $uid): int
    {
        return (int)(new Query())
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['uid' => $uid])
            ->count();
    }
}
