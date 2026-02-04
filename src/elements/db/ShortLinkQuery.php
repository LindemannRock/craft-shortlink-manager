<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\elements\db;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use lindemannrock\shortlinkmanager\elements\ShortLink;

/**
 * ShortLinkQuery element query
 *
 * @method ShortLink[]|array all($db = null)
 * @method ShortLink|array|null one($db = null)
 * @method ShortLink|array|null nth(int $n, ?Connection $db = null)
 * @since 5.0.0
 */
class ShortLinkQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    /**
     * @var string|string[]|null The slug(s) that the resulting short links must have.
     */
    public mixed $slug = null;

    /**
     * @var string|string[]|null The link type(s) that the resulting short links must have ('code' or 'vanity').
     */
    public mixed $linkType = null;

    /**
     * @var string|string[]|null The shortlink type(s) that the resulting short links must have ('auto' or 'manual').
     */
    public mixed $shortLinkType = null;

    /**
     * @var int|int[]|null The element ID(s) that the resulting short links must be linked to.
     */
    public mixed $elementId = null;

    /**
     * @var bool|null Whether the resulting short links must be expired.
     */
    public ?bool $expired = null;

    /**
     * @var int|int[]|null The HTTP code(s) that the resulting short links must have.
     */
    public mixed $httpCode = null;

    /**
     * @var bool|null Whether the resulting short links must have analytics tracking enabled.
     */
    public ?bool $trackAnalytics = null;

    // Public Methods
    // =========================================================================

    /**
     * Sets the [[slug]] property.
     *
     * @param string|string[]|null $value
     * @return static
     * @since 5.0.0
     */
    public function slug(mixed $value): static
    {
        $this->slug = $value;
        return $this;
    }

    /**
     * Sets the [[linkType]] property.
     *
     * @param string|string[]|null $value
     * @return static
     * @since 5.0.0
     */
    public function linkType(mixed $value): static
    {
        $this->linkType = $value;
        return $this;
    }

    /**
     * Sets the [[shortLinkType]] property.
     *
     * @param string|string[]|null $value
     * @return static
     * @since 5.0.0
     */
    public function shortLinkType(mixed $value): static
    {
        $this->shortLinkType = $value;
        return $this;
    }

    /**
     * Sets the [[elementId]] property.
     *
     * @param int|int[]|null $value
     * @return static
     * @since 5.0.0
     */
    public function elementId(mixed $value): static
    {
        $this->elementId = $value;
        return $this;
    }

    /**
     * Note: elementType() method is inherited from ElementQuery
     * We use the linked element filtering via elementId instead
     */

    /**
     * Sets the [[expired]] property.
     *
     * @param bool|null $value
     * @return static
     * @since 5.0.0
     */
    public function expired(?bool $value = true): static
    {
        $this->expired = $value;
        return $this;
    }

    /**
     * Sets the [[httpCode]] property.
     *
     * @param int|int[]|null $value
     * @return static
     * @since 5.0.0
     */
    public function httpCode(mixed $value): static
    {
        $this->httpCode = $value;
        return $this;
    }

    /**
     * Sets the [[trackAnalytics]] property.
     *
     * @param bool|null $value
     * @return static
     * @since 5.0.0
     */
    public function trackAnalytics(?bool $value = true): static
    {
        $this->trackAnalytics = $value;
        return $this;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status): mixed
    {
        // Always consider "now" to be the current time @ 59 seconds into the minute.
        // This makes shortlink queries more cacheable, since they only change once every
        // minute, while not excluding shortlinks published in the past minute.
        $now = new \DateTime();
        $now->setTime((int)$now->format('H'), (int)$now->format('i'), 59);
        $currentTimeDb = Db::prepareDateForDb($now);

        return match ($status) {
            ShortLink::STATUS_ENABLED => [
                'and',
                ['elements.enabled' => true, 'elements_sites.enabled' => true],
                ['<=', 'shortlinkmanager.postDate', $currentTimeDb],
                ['or', ['shortlinkmanager.dateExpired' => null], ['>', 'shortlinkmanager.dateExpired', $currentTimeDb]],
            ],
            ShortLink::STATUS_PENDING => [
                'and',
                ['elements.enabled' => true, 'elements_sites.enabled' => true],
                ['>', 'shortlinkmanager.postDate', $currentTimeDb],
            ],
            ShortLink::STATUS_EXPIRED => [
                'and',
                ['elements.enabled' => true, 'elements_sites.enabled' => true],
                ['not', ['shortlinkmanager.dateExpired' => null]],
                ['<=', 'shortlinkmanager.dateExpired', $currentTimeDb],
            ],
            default => parent::statusCondition($status),
        };
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        // Join in the shortlinkmanager table
        $this->joinElementTable('shortlinkmanager');

        // Join content table for site-specific data (LEFT JOIN so we get all shortlinks even if no content yet)
        $this->query->leftJoin(
            '{{%shortlinkmanager_content}} shortlinkmanager_content',
            '[[shortlinkmanager_content.shortLinkId]] = [[elements.id]] AND [[shortlinkmanager_content.siteId]] = [[elements_sites.siteId]]'
        );

        // Select columns from both tables
        // Note: elementId and elementType are now per-site (stored in content table)
        $this->query->select([
            'shortlinkmanager.code',
            'shortlinkmanager.slug',
            'shortlinkmanager.linkType',
            'shortlinkmanager.shortLinkType',
            'shortlinkmanager.authorId',
            'shortlinkmanager.postDate',
            'shortlinkmanager.dateExpired',
            'shortlinkmanager.httpCode',
            'shortlinkmanager.trackAnalytics',
            'shortlinkmanager.passQueryParams',
            'shortlinkmanager.hits',
            'shortlinkmanager.qrCodeEnabled',
            'shortlinkmanager.qrCodeSize',
            'shortlinkmanager.qrCodeColor',
            'shortlinkmanager.qrCodeBgColor',
            'shortlinkmanager.qrCodeEyeColor',
            'shortlinkmanager.qrCodeFormat',
            'shortlinkmanager.qrLogoId',
            'shortlinkmanager_content.elementId',
            'shortlinkmanager_content.elementType',
            'shortlinkmanager_content.destinationUrl',
            'shortlinkmanager_content.expiredRedirectUrl',
            'shortlinkmanager_content.expiredMessage',
            // Ensure we get the enabled status from elements_sites for current site
            'elements_sites.enabled',
        ]);

        // Apply custom filters
        if ($this->slug) {
            $this->subQuery->andWhere(Db::parseParam('shortlinkmanager.slug', $this->slug));
        }

        if ($this->linkType) {
            $this->subQuery->andWhere(Db::parseParam('shortlinkmanager.linkType', $this->linkType));
        }

        if ($this->shortLinkType) {
            $this->subQuery->andWhere(Db::parseParam('shortlinkmanager.shortLinkType', $this->shortLinkType));
        }

        if ($this->elementId) {
            // elementId is now in the content table (per-site)
            // Use EXISTS subquery to filter by elementId since elements_sites may not be joined yet
            $this->subQuery->andWhere([
                'exists',
                (new Query())
                    ->from('{{%shortlinkmanager_content}} slm_content_filter')
                    ->where('[[slm_content_filter.shortLinkId]] = [[elements.id]]')
                    ->andWhere(Db::parseParam('slm_content_filter.elementId', $this->elementId)),
            ]);
        }

        if ($this->httpCode) {
            $this->subQuery->andWhere(Db::parseParam('shortlinkmanager.httpCode', $this->httpCode));
        }

        if ($this->trackAnalytics !== null) {
            $this->subQuery->andWhere(Db::parseParam('shortlinkmanager.trackAnalytics', (int)$this->trackAnalytics));
        }

        if ($this->expired !== null) {
            if ($this->expired) {
                // Only expired links
                $this->subQuery->andWhere([
                    'and',
                    ['not', ['shortlinkmanager.dateExpired' => null]],
                    ['<', 'shortlinkmanager.dateExpired', Db::prepareDateForDb(new \DateTime())],
                ]);
            } else {
                // Only non-expired links
                $this->subQuery->andWhere([
                    'or',
                    ['shortlinkmanager.dateExpired' => null],
                    ['>=', 'shortlinkmanager.dateExpired', Db::prepareDateForDb(new \DateTime())],
                ]);
            }
        }

        return parent::beforePrepare();
    }
}
