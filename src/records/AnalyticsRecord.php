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
 * Analytics Record
 *
 * @property int $id
 * @property int $linkId
 * @property int $siteId
 * @property string|null $destinationUrl
 * @property string|null $ip
 * @property string|null $userAgent
 * @property string|null $referrer
 * @property string|null $language
 * @property string|null $deviceType
 * @property string|null $deviceBrand
 * @property string|null $deviceModel
 * @property string|null $browser
 * @property string|null $browserVersion
 * @property string|null $browserEngine
 * @property string|null $osName
 * @property string|null $osVersion
 * @property string|null $clientType
 * @property bool $isRobot
 * @property bool $isMobileApp
 * @property string|null $botName
 * @property string|null $botCategory
 * @property string|null $botUrl
 * @property string|null $botProducerName
 * @property string|null $botProducerUrl
 * @property bool|null $isSystemAgent
 * @property string $trafficType
 * @property string|null $country
 * @property string|null $city
 * @property string|null $region
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $metadata
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string|null $uid
 * @since 5.0.0
 */
class AnalyticsRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%shortlinkmanager_analytics}}';
    }
}
