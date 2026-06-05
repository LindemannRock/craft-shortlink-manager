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
use craft\helpers\Db;
use craft\helpers\StringHelper;
use lindemannrock\shortlinkmanager\services\analytics\AnalyticsQueryInsightsService;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins that getClickStats() applies the requested date window to the breakdown
 * sub-queries, not just the total.
 *
 * Regression: the device/browser/referrer/geo breakdowns ignored `filters` and
 * returned all-time data even when a narrow window was requested.
 *
 * @since 5.20.0
 */
final class ClickStatsDateFilterTest extends TestCase
{
    public function testBreakdownsRespectTheDateWindow(): void
    {
        $link = $this->seedShortLink([
            'code' => 'sl-test-clickstats',
            'slug' => 'sl-test-clickstats',
        ]);

        // One click inside a 7-day window, one well outside it.
        $this->seedAnalyticsRow((int) $link->id, 'desktop', new \DateTime('-1 day'));
        $this->seedAnalyticsRow((int) $link->id, 'mobile', new \DateTime('-60 days'));

        $stats = (new AnalyticsQueryInsightsService())->getClickStats((int) $link->id, ['days' => 7]);

        // Total already honored the window before the fix...
        self::assertSame(1, (int) $stats['totalClicks']);

        // ...but the device breakdown must now also exclude the 60-day-old row.
        $devices = array_column($stats['deviceBreakdown'], 'count', 'deviceType');
        self::assertSame(['desktop' => 1], array_map('intval', $devices));
    }

    private function seedAnalyticsRow(int $linkId, string $deviceType, \DateTime $dateCreated): void
    {
        Craft::$app->db->createCommand()->insert('{{%shortlinkmanager_analytics}}', [
            'linkId' => $linkId,
            'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
            'deviceType' => $deviceType,
            'dateCreated' => Db::prepareDateForDb($dateCreated),
            'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
            'uid' => StringHelper::UUID(),
        ])->execute();
    }
}
