<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
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
 * @property string $destinationUrl
 * @property string|null $expiredRedirectUrl
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
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
