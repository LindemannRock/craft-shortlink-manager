<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 */

namespace lindemannrock\shortlinkmanager\records;

use craft\db\ActiveRecord;

/**
 * Import history record
 *
 * @property int $id
 * @property int|null $userId
 * @property string|null $filename
 * @property int|null $filesize
 * @property int $imported
 * @property int $failed
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 *
 * @since 5.15.0
 */
class ImportHistoryRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%shortlinkmanager_import_history}}';
    }
}
