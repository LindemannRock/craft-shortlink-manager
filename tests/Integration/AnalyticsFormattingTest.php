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
use craft\helpers\StringHelper;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins the timezone-aware SQL grouping behind the analytics
 * `DateFormatHelper::localDateExpression()` / `localHourExpression()` callers
 * (`AnalyticsQueryInsightsService::getClicksData()`, `getHourlyAnalytics()`,
 * `getClickStats()`), exercised under non-default date/time settings.
 *
 * These methods produce grouping keys (local-date `Y-m-d` labels, local-hour
 * buckets); the cascade-aware *display* formatting happens later in the chart/
 * template layer via `lrDate`/`lrTime` (covered by ElementIndexDateFormattingTest
 * and the analytics Twig). This test confirms the grouping is correct and that
 * the queries run cleanly when the plugin runs under a non-default cascade.
 *
 * @since 5.20.0
 */
final class AnalyticsFormattingTest extends TestCase
{
    public function testClicksDataAndHourlyGroupByLocalDateAndHourUnderCustomSettings(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $link = $this->seedShortLink([
            'code' => 'sl-test-analytics-grouping',
            'slug' => 'sl-test-analytics-grouping',
        ]);

        $tz = new \DateTimeZone(Craft::$app->getTimeZone());
        $today = (new \DateTime('now', $tz))->format('Y-m-d');
        // Two clicks in the same local hour today.
        $this->seedAnalyticsRow((int) $link->id, (int) $site->id, new \DateTime('today 15:15:00', $tz));
        $this->seedAnalyticsRow((int) $link->id, (int) $site->id, new \DateTime('today 15:40:00', $tz));

        $this->withSettings([
            'monthFormat' => 'long',
            'dateOrder' => 'dmy',
            'dateSeparator' => '.',
            'timeFormat' => '24',
        ], function () use ($link, $site, $today): void {
            // localDateExpression grouping (getClicksData, :394).
            $clicks = $this->analytics->getClicksData((int) $link->id, 'today', (int) $site->id);
            self::assertSame([$today], $clicks['labels'], 'Clicks should bucket onto the local date.');
            self::assertSame([2], $clicks['values']);

            // localHourExpression grouping (getHourlyAnalytics, :471).
            $hourly = $this->analytics->getHourlyAnalytics((int) $link->id, 'today', (int) $site->id);
            self::assertSame(15, $hourly['peakHour'], 'Peak hour should be the local hour of the clicks.');
            self::assertSame(2, $hourly['data'][15]);
            self::assertSame('15:00', $hourly['peakHourFormatted']);

            // localDateExpression grouping inside getClickStats (:66).
            $stats = $this->analytics->getClickStats((int) $link->id, ['days' => 1]);
            self::assertSame(2, (int) $stats['totalClicks']);
            self::assertNotEmpty($stats['clicksByDate'], 'clicksByDate should be grouped by local date.');
        });
    }

    private function seedAnalyticsRow(int $linkId, int $siteId, \DateTime $localDateTime): void
    {
        $utc = (clone $localDateTime)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        Craft::$app->db->createCommand()->insert('{{%shortlinkmanager_analytics}}', [
            'linkId' => $linkId,
            'siteId' => $siteId,
            'deviceType' => 'desktop',
            'dateCreated' => $utc,
            'dateUpdated' => $utc,
            'uid' => StringHelper::UUID(),
        ])->execute();
    }
}
