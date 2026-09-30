<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use Craft;
use craft\db\Connection;
use craft\db\Query;
use lindemannrock\shortlinkmanager\migrations\m260930_000000_seomatic_event_prefix_default;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * @since 5.28.5
 */
final class SeomaticEventPrefixTest extends TestCase
{
    public function testNewSettingsUseShortLinksPrefix(): void
    {
        self::assertSame('short_links', (new Settings())->seomaticEventPrefix);
        self::assertSame('shortlink_manager', (new Settings(['seomaticEventPrefix' => 'shortlink_manager']))->seomaticEventPrefix);
        self::assertSame('custom_campaign', (new Settings(['seomaticEventPrefix' => 'custom_campaign']))->seomaticEventPrefix);
    }

    public function testDefaultMigrationPreservesCompleteExistingRows(): void
    {
        $source = Craft::$app->getDb();
        $prefix = 'sl_prefix_' . bin2hex(random_bytes(8)) . '_';
        $db = new Connection([
            'dsn' => $source->dsn,
            'username' => $source->username,
            'password' => $source->password,
            'tablePrefix' => $prefix,
            'charset' => $source->charset,
        ]);
        $table = $db->quoteTableName('{{%shortlinkmanager_settings}}');
        try {
            // Clone every real settings column, without touching the source table.
            $db->createCommand('CREATE TABLE ' . $table . ' AS SELECT * FROM '
                . $source->quoteTableName($source->getSchema()->getRawTableName('{{%shortlinkmanager_settings}}')) . ' WHERE 1 = 0')->execute();
            $migration = new m260930_000000_seomatic_event_prefix_default(['db' => $db, 'compact' => true]);
            self::assertTrue($migration->safeDown());
            $row = (new Query())->from('{{%shortlinkmanager_settings}}')->where(['id' => 1])->one($source);
            self::assertIsArray($row);
            foreach (['shortlink_manager', 'custom_campaign', 'short_links'] as $index => $value) {
                $row['id'] = $index + 1;
                $row['seomaticEventPrefix'] = $value;
                $db->createCommand()->insert('{{%shortlinkmanager_settings}}', $row)->execute();
            }
            $before = (new Query())->from('{{%shortlinkmanager_settings}}')->orderBy('id')->all($db);
            self::assertTrue($migration->safeUp());
            self::assertSame($before, (new Query())->from('{{%shortlinkmanager_settings}}')->orderBy('id')->all($db));
            self::assertSame('short_links', $db->getSchema()->getTableSchema('{{%shortlinkmanager_settings}}', true)->columns['seomaticEventPrefix']->defaultValue);

            unset($row['seomaticEventPrefix']);
            $row['id'] = 4;
            $db->createCommand()->insert('{{%shortlinkmanager_settings}}', $row)->execute();
            self::assertSame('short_links', (new Query())->select('seomaticEventPrefix')->from('{{%shortlinkmanager_settings}}')->where(['id' => 4])->scalar($db));
            $upgraded = (new Query())->from('{{%shortlinkmanager_settings}}')->orderBy('id')->all($db);
            self::assertTrue($migration->safeDown());
            self::assertSame($upgraded, (new Query())->from('{{%shortlinkmanager_settings}}')->orderBy('id')->all($db));
            self::assertSame('shortlink_manager', $db->getSchema()->getTableSchema('{{%shortlinkmanager_settings}}', true)->columns['seomaticEventPrefix']->defaultValue);
        } finally {
            try {
                if ($db->getSchema()->getTableSchema('{{%shortlinkmanager_settings}}', true) !== null) {
                    $db->createCommand()->dropTable('{{%shortlinkmanager_settings}}')->execute();
                    self::assertNull($db->getSchema()->getTableSchema('{{%shortlinkmanager_settings}}', true));
                }
            } finally {
                $db->close();
            }
        }
    }
}
