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

    /**
     * @var string|null Requested status for custom handling
     */
    private ?string $_requestedStatus = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function status($value): static
    {
        // Store the requested status for use in beforePrepare
        $this->_requestedStatus = $value;

        // For custom statuses (expired), don't pass to parent yet
        // We'll handle the filtering in beforePrepare()
        if ($value === ShortLink::STATUS_EXPIRED) {
            // Set to null so parent doesn't filter by status
            return parent::status(null);
        }

        return parent::status($value);
    }

    /**
     * Sets the [[slug]] property.
     *
     * @param string|string[]|null $value
     * @return static
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
     */
    public function linkType(mixed $value): static
    {
        $this->linkType = $value;
        return $this;
    }

    /**
     * Sets the [[elementId]] property.
     *
     * @param int|int[]|null $value
     * @return static
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
    protected function beforePrepare(): bool
    {
        // Join in the shortlinkmanager_links table
        $this->joinElementTable('shortlinkmanager_links');

        // Join content table for site-specific data (LEFT JOIN so we get all shortlinks even if no content yet)
        $this->query->leftJoin(
            '{{%shortlinkmanager_content}} shortlinkmanager_content',
            '[[shortlinkmanager_content.shortLinkId]] = [[elements.id]] AND [[shortlinkmanager_content.siteId]] = [[elements_sites.siteId]]'
        );

        // Select columns from both tables
        $this->query->select([
            'shortlinkmanager_links.code',
            'shortlinkmanager_links.slug',
            'shortlinkmanager_links.linkType',
            'shortlinkmanager_links.elementId',
            'shortlinkmanager_links.elementType',
            'shortlinkmanager_links.authorId',
            'shortlinkmanager_links.postDate',
            'shortlinkmanager_links.dateExpired',
            'shortlinkmanager_links.httpCode',
            'shortlinkmanager_links.trackAnalytics',
            'shortlinkmanager_links.hits',
            'shortlinkmanager_links.qrCodeEnabled',
            'shortlinkmanager_links.qrCodeSize',
            'shortlinkmanager_links.qrCodeColor',
            'shortlinkmanager_links.qrCodeBgColor',
            'shortlinkmanager_links.qrCodeEyeColor',
            'shortlinkmanager_links.qrCodeFormat',
            'shortlinkmanager_links.qrLogoId',
            'shortlinkmanager_content.destinationUrl',
            'shortlinkmanager_content.expiredRedirectUrl',
            // Ensure we get the enabled status from elements_sites for current site
            'elements_sites.enabled',
        ]);

        // Apply custom filters
        if ($this->slug) {
            $this->subQuery->andWhere(Db::parseParam('shortlinkmanager_links.slug', $this->slug));
        }

        if ($this->linkType) {
            $this->subQuery->andWhere(Db::parseParam('shortlinkmanager_links.linkType', $this->linkType));
        }

        if ($this->elementId) {
            $this->subQuery->andWhere(Db::parseParam('shortlinkmanager_links.elementId', $this->elementId));
        }

        if ($this->httpCode) {
            $this->subQuery->andWhere(Db::parseParam('shortlinkmanager_links.httpCode', $this->httpCode));
        }

        if ($this->trackAnalytics !== null) {
            $this->subQuery->andWhere(Db::parseParam('shortlinkmanager_links.trackAnalytics', $this->trackAnalytics));
        }

        if ($this->expired !== null) {
            if ($this->expired) {
                // Only expired links
                $this->subQuery->andWhere([
                    'and',
                    ['not', ['shortlinkmanager_links.dateExpired' => null]],
                    ['<', 'shortlinkmanager_links.dateExpired', Db::prepareDateForDb(new \DateTime())],
                ]);
            } else {
                // Only non-expired links
                $this->subQuery->andWhere([
                    'or',
                    ['shortlinkmanager_links.dateExpired' => null],
                    ['>=', 'shortlinkmanager_links.dateExpired', Db::prepareDateForDb(new \DateTime())],
                ]);
            }
        }

        // Handle custom statuses
        if ($this->_requestedStatus === ShortLink::STATUS_EXPIRED) {
            // Show only expired items (must have dateExpired in the past)
            $this->subQuery->andWhere(['<', 'shortlinkmanager_links.dateExpired', new \yii\db\Expression('NOW()')]);
        }

        return parent::beforePrepare();
    }
}
