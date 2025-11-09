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
 * ShortLink Record
 *
 * Stores non-translatable data for short links
 *
 * @property int $id
 * @property string|null $code
 * @property string $slug
 * @property string $linkType
 * @property int|null $elementId
 * @property string|null $elementType
 * @property int|null $authorId
 * @property \DateTime|null $postDate
 * @property \DateTime|null $dateExpired
 * @property int $httpCode
 * @property bool $trackAnalytics
 * @property int $hits
 * @property bool $qrCodeEnabled
 * @property int $qrCodeSize
 * @property string $qrCodeColor
 * @property string $qrCodeBgColor
 * @property string|null $qrCodeEyeColor
 * @property string|null $qrCodeFormat
 * @property int|null $qrLogoId
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class ShortLinkRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%shortlinkmanager_links}}';
    }
}
