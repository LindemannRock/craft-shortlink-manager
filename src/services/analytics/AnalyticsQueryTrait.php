<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services\analytics;

use craft\db\Query;
use lindemannrock\base\helpers\DateRangeHelper;

/**
 * Shared query utilities for analytics sub-services
 *
 * @author    LindemannRock
 * @package   ShortLinkManager
 * @since     5.0.0
 */
trait AnalyticsQueryTrait
{
    /**
     * Apply date range filter to query
     *
     * @param Query $query
     * @param string $dateRange
     * @param string $column
     */
    public function applyDateRangeFilter(Query $query, string $dateRange, string $column = 'dateCreated'): void
    {
        DateRangeHelper::applyToQuery($query, $dateRange, $column);
    }

    /**
     * Get number of days for a date range
     *
     * @param string $dateRange
     * @return int
     */
    private function getDaysCount(string $dateRange): int
    {
        $bounds = DateRangeHelper::getBounds($dateRange);
        $start = $bounds['start'] ?? null;
        $end = $bounds['end'] ?? null;

        if (!$start && !$end) {
            return 36500;
        }

        $end = $end ?? new \DateTime('now', new \DateTimeZone('UTC'));

        if (!$start) {
            return 30;
        }

        $days = (int) $start->diff($end)->format('%a');

        return max(1, $days);
    }
}
