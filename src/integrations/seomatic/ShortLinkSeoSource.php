<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\integrations\seomatic;

use Craft;
use craft\base\Model;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Synthetic SEOmatic source model for route-backed ShortLink elements.
 *
 * @since 5.22.0
 */
class ShortLinkSeoSource extends Model
{
    public int $id = SeoShortLink::SOURCE_ID;
    public string $handle = SeoShortLink::SOURCE_HANDLE;
    public string $type = SeoShortLink::SOURCE_TYPE;

    /**
     * @return array<int, ShortLinkSeoSourceSiteSettings>
     */
    public function getSiteSettings(): array
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $siteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteSettings[$site->id] = new ShortLinkSeoSourceSiteSettings([
                'siteId' => $site->id,
                'hasUrls' => $settings->isSiteEnabled((int) $site->id),
            ]);
        }

        return $siteSettings;
    }

    public function getName(): string
    {
        return ShortLinkManager::$plugin->getSettings()->getPluralDisplayName();
    }
}
