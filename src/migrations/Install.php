<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\migrations;

use craft\db\Migration;
use craft\helpers\Db;
use craft\helpers\StringHelper;

/**
 * ShortLink Manager Install Migration (Element-based)
 *
 * @author    LindemannRock
 * @package   ShortLinkManager
 * @since     2.0.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create the shortlinkmanager table (element-based structure)
        if (!$this->db->tableExists('{{%shortlinkmanager}}')) {
            $this->createTable('{{%shortlinkmanager}}', [
                'id' => $this->integer()->notNull(),
                'code' => $this->string(100)->null(),
                'slug' => $this->string(100)->notNull(),
                'linkType' => $this->string(10)->notNull()->defaultValue('code'),
                'elementId' => $this->integer()->null(),
                'elementType' => $this->string()->null(),
                'authorId' => $this->integer()->null(),
                'postDate' => $this->dateTime()->null(),
                'dateExpired' => $this->dateTime()->null(),
                'httpCode' => $this->integer()->notNull()->defaultValue(301),
                'trackAnalytics' => $this->boolean()->notNull()->defaultValue(true),
                'hits' => $this->integer()->notNull()->defaultValue(0),
                // QR Code settings (per-link)
                'qrCodeEnabled' => $this->boolean()->notNull()->defaultValue(true),
                'qrCodeSize' => $this->integer()->notNull()->defaultValue(256),
                'qrCodeColor' => $this->string(7)->null(),
                'qrCodeBgColor' => $this->string(7)->null(),
                'qrCodeEyeColor' => $this->string(7)->null(),
                'qrCodeFormat' => $this->string(10)->null(),
                'qrLogoId' => $this->integer()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'PRIMARY KEY(id)',
            ]);

            // Create indexes
            $this->createIndex(null, '{{%shortlinkmanager}}', 'code');
            $this->createIndex(null, '{{%shortlinkmanager}}', 'slug', true);
            $this->createIndex(null, '{{%shortlinkmanager}}', 'elementId');
            $this->createIndex(null, '{{%shortlinkmanager}}', 'authorId');
            $this->createIndex(null, '{{%shortlinkmanager}}', 'postDate');
            $this->createIndex(null, '{{%shortlinkmanager}}', 'dateExpired');
            $this->createIndex(null, '{{%shortlinkmanager}}', 'linkType');
            $this->createIndex(null, '{{%shortlinkmanager}}', 'qrLogoId');

            // Add foreign keys
            $this->addForeignKey(null, '{{%shortlinkmanager}}', 'id', '{{%elements}}', 'id', 'CASCADE');
            $this->addForeignKey(null, '{{%shortlinkmanager}}', 'authorId', '{{%users}}', 'id', 'SET NULL');
            $this->addForeignKey(null, '{{%shortlinkmanager}}', 'elementId', '{{%elements}}', 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, '{{%shortlinkmanager}}', 'qrLogoId', '{{%assets}}', 'id', 'SET NULL', 'CASCADE');
        }

        // Create the shortlinkmanager_content table for site-specific/translatable data
        if (!$this->db->tableExists('{{%shortlinkmanager_content}}')) {
            $this->createTable('{{%shortlinkmanager_content}}', [
                'id' => $this->primaryKey(),
                'shortLinkId' => $this->integer()->notNull(),
                'siteId' => $this->integer()->notNull(),
                'destinationUrl' => $this->text()->notNull(),
                'expiredRedirectUrl' => $this->string()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Create indexes
            $this->createIndex(null, '{{%shortlinkmanager_content}}', ['shortLinkId', 'siteId'], true);
            $this->createIndex(null, '{{%shortlinkmanager_content}}', 'siteId');

            // Add foreign keys
            $this->addForeignKey(null, '{{%shortlinkmanager_content}}', 'shortLinkId', '{{%shortlinkmanager}}', 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, '{{%shortlinkmanager_content}}', 'siteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        }

        // Create the shortlinkmanager_analytics table
        if (!$this->db->tableExists('{{%shortlinkmanager_analytics}}')) {
            $this->createTable('{{%shortlinkmanager_analytics}}', [
                'id' => $this->primaryKey(),
                'linkId' => $this->integer()->notNull(),
                'siteId' => $this->integer()->notNull(),
                'ip' => $this->string(64)->null(),
                'userAgent' => $this->text()->null(),
                'metadata' => $this->text()->null(),
                'referer' => $this->string()->null(),
                'deviceType' => $this->string(50)->null(),
                'deviceBrand' => $this->string(50)->null(),
                'deviceModel' => $this->string(100)->null(),
                'browser' => $this->string(100)->null(),
                'browserVersion' => $this->string(20)->null(),
                'browserEngine' => $this->string(50)->null(),
                'osName' => $this->string(50)->null(),
                'osVersion' => $this->string(50)->null(),
                'clientType' => $this->string(50)->null(),
                'isRobot' => $this->boolean()->defaultValue(false),
                'isMobileApp' => $this->boolean()->defaultValue(false),
                'botName' => $this->string(100)->null(),
                'country' => $this->string(2)->null(),
                'city' => $this->string(100)->null(),
                'language' => $this->string(10)->null(),
                'region' => $this->string(100)->null(),
                'latitude' => $this->decimal(10, 8)->null(),
                'longitude' => $this->decimal(11, 8)->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Create indexes for performance
            $this->createIndex(null, '{{%shortlinkmanager_analytics}}', 'linkId');
            $this->createIndex(null, '{{%shortlinkmanager_analytics}}', 'siteId');
            $this->createIndex(null, '{{%shortlinkmanager_analytics}}', 'deviceType');
            $this->createIndex(null, '{{%shortlinkmanager_analytics}}', 'deviceBrand');
            $this->createIndex(null, '{{%shortlinkmanager_analytics}}', 'osName');
            $this->createIndex(null, '{{%shortlinkmanager_analytics}}', 'clientType');
            $this->createIndex(null, '{{%shortlinkmanager_analytics}}', 'isRobot');
            $this->createIndex(null, '{{%shortlinkmanager_analytics}}', 'country');
            $this->createIndex(null, '{{%shortlinkmanager_analytics}}', 'dateCreated');

            // Add foreign keys
            $this->addForeignKey(null, '{{%shortlinkmanager_analytics}}', 'linkId', '{{%shortlinkmanager}}', 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, '{{%shortlinkmanager_analytics}}', 'siteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        }

        // Create the shortlinkmanager_settings table
        if (!$this->db->tableExists('{{%shortlinkmanager_settings}}')) {
            $this->createTable('{{%shortlinkmanager_settings}}', [
                'id' => $this->primaryKey(),
                // Plugin settings
                'pluginName' => $this->string(255)->notNull()->defaultValue('Short Links'),
                // Site settings
                'enabledSites' => $this->text()->null()->comment('JSON array of enabled site IDs'),
                // URL settings
                'slugPrefix' => $this->string(50)->notNull()->defaultValue('s'),
                'qrPrefix' => $this->string(50)->notNull()->defaultValue('sqr')->comment('URL prefix for QR code pages (e.g., "sqr" or "s/qr")'),
                'codeLength' => $this->integer()->notNull()->defaultValue(8),
                'customDomain' => $this->string()->null(),
                'reservedCodes' => $this->text()->null()->comment('JSON array of reserved codes'),
                // QR Code settings
                'enableQrCodes' => $this->boolean()->notNull()->defaultValue(true),
                'defaultQrSize' => $this->integer()->notNull()->defaultValue(256),
                'defaultQrColor' => $this->string(7)->notNull()->defaultValue('#000000'),
                'defaultQrBgColor' => $this->string(7)->notNull()->defaultValue('#FFFFFF'),
                'defaultQrFormat' => $this->string(3)->notNull()->defaultValue('png'),
                'defaultQrErrorCorrection' => $this->string(1)->notNull()->defaultValue('M'),
                'defaultQrMargin' => $this->integer()->notNull()->defaultValue(4),
                'qrModuleStyle' => $this->string(10)->notNull()->defaultValue('square'),
                'qrEyeStyle' => $this->string(10)->notNull()->defaultValue('square'),
                'qrEyeColor' => $this->string(7)->null(),
                'enableQrLogo' => $this->boolean()->notNull()->defaultValue(false),
                'qrLogoVolumeUid' => $this->string()->null(),
                'defaultQrLogoId' => $this->integer()->null(),
                'qrLogoSize' => $this->integer()->notNull()->defaultValue(20),
                'enableQrCodeCache' => $this->boolean()->notNull()->defaultValue(true),
                'qrCodeCacheDuration' => $this->integer()->notNull()->defaultValue(86400),
                'enableQrDownload' => $this->boolean()->notNull()->defaultValue(true),
                'qrDownloadFilename' => $this->string()->notNull()->defaultValue('{code}-qr-{size}'),
                // Analytics settings
                'enableAnalytics' => $this->boolean()->notNull()->defaultValue(true),
                'analyticsRetention' => $this->integer()->notNull()->defaultValue(90),
                'anonymizeIpAddress' => $this->boolean()->notNull()->defaultValue(false),
                // Template settings
                'redirectTemplate' => $this->string(500)->null()->comment('Custom redirect template path'),
                'expiredTemplate' => $this->string(500)->null()->comment('Custom expired template path'),
                'qrTemplate' => $this->string(500)->null()->comment('Custom QR code template path'),
                // Device & Geo Detection
                'enableGeoDetection' => $this->boolean()->notNull()->defaultValue(false),
                'cacheDeviceDetection' => $this->boolean()->notNull()->defaultValue(true),
                'deviceDetectionCacheDuration' => $this->integer()->notNull()->defaultValue(3600),
                // Redirect/Behavior settings
                'defaultHttpCode' => $this->integer()->notNull()->defaultValue(301),
                'notFoundRedirectUrl' => $this->string()->notNull()->defaultValue('/'),
                'expiredMessage' => $this->text()->null(),
                // Interface settings
                'itemsPerPage' => $this->integer()->notNull()->defaultValue(50),
                // Integration settings
                'enabledIntegrations' => $this->text()->null()->comment('JSON array of enabled integration handles'),
                'redirectManagerEvents' => $this->text()->null()->comment('JSON array of redirect manager event types'),
                'seomaticTrackingEvents' => $this->text()->null()->comment('JSON array of event types to track in SEOmatic'),
                'seomaticEventPrefix' => $this->string(50)->defaultValue('shortlink_manager')->comment('Event prefix for GTM/GA events'),
                // Logging
                'logLevel' => $this->string(20)->notNull()->defaultValue('error'),
                // Timestamps
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Create indexes
            $this->createIndex(null, '{{%shortlinkmanager_settings}}', 'enableAnalytics');
            $this->createIndex(null, '{{%shortlinkmanager_settings}}', 'enableGeoDetection');

            // Add foreign key for logo
            $this->addForeignKey(null, '{{%shortlinkmanager_settings}}', 'defaultQrLogoId', '{{%assets}}', 'id', 'SET NULL');

            // Insert default settings row
            $this->insert('{{%shortlinkmanager_settings}}', [
                'expiredMessage' => 'This link has expired',
                'dateCreated' => Db::prepareDateForDb(new \DateTime()),
                'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
                'uid' => StringHelper::UUID(),
            ]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop tables in reverse order due to foreign key constraints
        $this->dropTableIfExists('{{%shortlinkmanager_analytics}}');
        $this->dropTableIfExists('{{%shortlinkmanager_content}}');
        $this->dropTableIfExists('{{%shortlinkmanager_settings}}');
        $this->dropTableIfExists('{{%shortlinkmanager}}');

        return true;
    }
}
