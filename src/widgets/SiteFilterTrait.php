<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\widgets;

use Craft;
use lindemannrock\base\helpers\UrlSafetyHelper;
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
        $siteIds = array_map(
            static fn($site): int => (int) $site->id,
            ShortLinkManager::$plugin->getEnabledSites()
        );

        if ($this->siteId !== 'all') {
            $siteId = (int) $this->siteId;

            return in_array($siteId, $siteIds, true) ? $siteId : [];
        }

        return $siteIds;
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

    /**
     * @param array<int, array<string, mixed>> $links
     * @return array<int, array<string, mixed>>
     */
    protected function withSafeDestinationUrls(array $links): array
    {
        foreach ($links as $index => $link) {
            $destinationUrl = $link['destinationUrl'] ?? null;
            $links[$index]['safeDestinationUrl'] = is_string($destinationUrl) && UrlSafetyHelper::isSafeRedirectUrl($destinationUrl)
                ? trim($destinationUrl)
                : null;
        }

        return $links;
    }
}
