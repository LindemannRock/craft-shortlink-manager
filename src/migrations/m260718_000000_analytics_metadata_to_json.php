<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * Converts the analytics `metadata` column from text to a native JSON type.
 *
 * The column has always held JSON (every writer uses Json::encode()), but it
 * was declared text() — MySQL's JSON_EXTRACT() tolerates that, while
 * PostgreSQL's ->> operator requires a json/jsonb operand and errors with
 * "operator does not exist: text ->> unknown" (SQLSTATE 42883) on every
 * DbHelper::jsonExtract() query. Craft's json() maps to JSON on MySQL and
 * jsonb on PostgreSQL, so the existing ->> queries just work after this.
 *
 * Guarded: rows whose value is empty or not valid JSON are nulled first,
 * because both MySQL's JSON column validation and PostgreSQL's ::jsonb cast
 * hard-error on invalid input (none should exist — every writer encodes —
 * but a migration must not assume).
 */
class m260718_000000_analytics_metadata_to_json extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Null out anything the JSON cast/validation would choke on.
        $invalidIds = [];
        foreach ((new Query())
            ->select(['id', 'metadata'])
            ->from('{{%shortlinkmanager_analytics}}')
            ->where(['not', ['metadata' => null]])
            ->batch(500, $this->db) as $batch) {
            foreach ($batch as $row) {
                if ($row['metadata'] === '' || json_decode((string)$row['metadata']) === null && strtolower(trim((string)$row['metadata'])) !== 'null') {
                    $invalidIds[] = $row['id'];
                }
            }
        }

        if ($invalidIds !== []) {
            echo '    > nulling ' . count($invalidIds) . " non-JSON metadata row(s)\n";
            foreach (array_chunk($invalidIds, 500) as $chunk) {
                $this->update('{{%shortlinkmanager_analytics}}', ['metadata' => null], ['id' => $chunk], [], false);
            }
        }

        if ($this->db->getIsPgsql()) {
            // Yii's alterColumn() omits the USING clause PostgreSQL needs for
            // a text -> jsonb conversion.
            $this->execute('ALTER TABLE ' . $this->db->quoteTableName('{{%shortlinkmanager_analytics}}')
                . ' ALTER COLUMN [[metadata]] TYPE jsonb USING [[metadata]]::jsonb');
        } else {
            $this->alterColumn('{{%shortlinkmanager_analytics}}', 'metadata', $this->json()->null());
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->getIsPgsql()) {
            $this->execute('ALTER TABLE ' . $this->db->quoteTableName('{{%shortlinkmanager_analytics}}')
                . ' ALTER COLUMN [[metadata]] TYPE text USING [[metadata]]::text');
        } else {
            $this->alterColumn('{{%shortlinkmanager_analytics}}', 'metadata', $this->text()->null());
        }

        return true;
    }
}
