<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\variables;

use craft\base\ElementInterface;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\elements\db\ShortLinkQuery;

/**
 * ShortLink Manager Variable
 *
 * Provides Twig API for shortlinks
 */
class ShortLinkManagerVariable
{
    /**
     * Query shortlinks
     *
     * @param array $criteria
     * @return ShortLinkQuery
     */
    public function shortLinks(array $criteria = []): ShortLinkQuery
    {
        $query = ShortLink::find();

        if (!empty($criteria)) {
            \Craft::configure($query, $criteria);
        }

        return $query;
    }

    /**
     * Get or create a shortlink
     *
     * @param array $options
     * @return ShortLink|null
     */
    public function get(array $options = []): ?ShortLink
    {
        // If element provided, try to get existing link first
        if (isset($options['element']) && $options['element'] instanceof ElementInterface) {
            $element = $options['element'];
            $siteId = $options['siteId'] ?? $element->siteId ?? null;

            $existing = ShortLinkManager::$plugin->shortLinks->getByElement($element, $siteId);

            if ($existing) {
                return $existing;
            }

            // Create new if not found
            return $this->create($options);
        }

        // Get by code/slug
        if (isset($options['code']) || isset($options['slug'])) {
            $slug = $options['slug'] ?? $options['code'];
            $siteId = $options['siteId'] ?? null;
            return ShortLinkManager::$plugin->shortLinks->getBySlug($slug, $siteId);
        }

        // Get by ID
        if (isset($options['id'])) {
            $siteId = $options['siteId'] ?? null;
            return ShortLinkManager::$plugin->shortLinks->getById($options['id'], $siteId);
        }

        return null;
    }

    /**
     * Create a new shortlink
     *
     * @param array $options
     * @return ShortLink|null
     */
    public function create(array $options = []): ?ShortLink
    {
        return ShortLinkManager::$plugin->shortLinks->createShortLink($options);
    }

    /**
     * Get analytics for a link
     *
     * @param int $linkId
     * @param array $filters
     * @return array
     */
    public function getAnalytics(int $linkId, array $filters = []): array
    {
        return ShortLinkManager::$plugin->analytics->getClickStats($linkId, $filters);
    }

    /**
     * Get all shortlinks
     *
     * @param array $criteria
     * @return array
     */
    public function getAll(array $criteria = []): array
    {
        return ShortLinkManager::$plugin->shortLinks->getAll($criteria);
    }
}
