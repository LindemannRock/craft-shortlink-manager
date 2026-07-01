<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\widgets;

use Craft;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Shared site filter behavior for ShortLink Manager dashboard widgets.
 *
 * @since 5.21.0
 */
trait SiteFilterTrait
{
    /**
     * @var string Selected site ID, or "all" for all enabled/editable sites
     */
    public string $siteId = 'all';

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function siteOptions(): array
    {
        $options = [
            ['value' => 'all', 'label' => Craft::t('lindemannrock-base', 'All Sites')],
        ];

        foreach (ShortLinkManager::$plugin->getEnabledSites() as $site) {
            $options[] = [
                'value' => (string) $site->id,
                'label' => $site->name,
            ];
        }

        return $options;
    }

    /**
     * @return int|array<int>
     */
    protected function effectiveSiteId(): int|array
    {
        if ($this->siteId !== 'all') {
            return (int) $this->siteId;
        }

        return array_map(
            static fn($site): int => (int) $site->id,
            ShortLinkManager::$plugin->getEnabledSites()
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyAnalyticsSummary(): array
    {
        return [
            'totalClicks' => 0,
            'uniqueVisitors' => 0,
            'activeLinks' => 0,
            'totalLinks' => 0,
            'linksUsed' => 0,
            'linksUsedPercentage' => 0,
            'topLinks' => [],
        ];
    }
}
