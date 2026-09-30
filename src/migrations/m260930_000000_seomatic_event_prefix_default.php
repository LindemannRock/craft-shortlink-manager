<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\migrations;

use craft\db\Migration;

/**
 * Aligns the default for future settings rows without rewriting saved prefixes.
 *
 * @since 5.28.5
 */
class m260930_000000_seomatic_event_prefix_default extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->execute('ALTER TABLE {{%shortlinkmanager_settings}} ALTER COLUMN [[seomaticEventPrefix]] SET DEFAULT '
            . $this->db->quoteValue('short_links'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->execute('ALTER TABLE {{%shortlinkmanager_settings}} ALTER COLUMN [[seomaticEventPrefix]] SET DEFAULT '
            . $this->db->quoteValue('shortlink_manager'));

        return true;
    }
}
