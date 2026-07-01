<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\records;

use craft\db\ActiveRecord;

/**
 * ShortLink Content Record
 *
 * Stores site-specific/translatable content for short links
 *
 * @property int $id
 * @property int $shortLinkId
 * @property int $siteId
 * @property int|null $elementId
 * @property string|null $elementType
 * @property string $destinationUrl
 * @property string|null $expiredRedirectUrl
 * @property string|null $expiredMessage
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 * @since 5.0.0
 */
class ShortLinkContentRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%shortlinkmanager_content}}';
    }
}
