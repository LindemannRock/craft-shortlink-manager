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
 * @since     5.13.0
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
}
