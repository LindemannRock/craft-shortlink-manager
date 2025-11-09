<?php
/**
 * ShortLink Manager config.php
 *
 * This file exists only as a template for the ShortLink Manager settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'shortlink-manager.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

use craft\helpers\App;

return [
    // Global settings
    '*' => [
        // ========================================
        // GENERAL SETTINGS
        // ========================================
        // Basic plugin configuration and URL settings

        'pluginName' => 'ShortLink Manager',

        // IP Privacy Protection
        // Generate salt with: php craft shortlink-manager/security/generate-salt
        // Store in .env as: SHORTLINK_MANAGER_IP_SALT="your-64-char-salt"
        'ipHashSalt' => App::env('SHORTLINK_MANAGER_IP_SALT'),

        // Site Settings
        'enabledSites' => [],          // Array of site IDs where ShortLink Manager should be enabled (empty = all sites)

        // URL Settings
        'slugPrefix' => 's',           // URL prefix for shortlinks (e.g., 's' creates /s/ABC123)
        'qrPrefix' => 'sqr',           // URL prefix for QR code pages (e.g., 'sqr' or 's/qr')
        'codeLength' => 8,             // Length of generated shortlink codes
        'customDomain' => '',          // Optional custom domain for shortlinks
        'reservedCodes' => ['admin', 'api', 'login', 'logout', 'cp', 'dashboard', 'settings'],

        // Template Settings
        'redirectTemplate' => null,    // Custom redirect template path (e.g., 'custom/redirect')
        'expiredTemplate' => null,     // Custom expired page template path (e.g., 'shortlink-manager/expired')
        'qrTemplate' => null,          // Custom QR code template path (e.g., 'shortlink-manager/qr')
        'expiredMessage' => 'This link has expired',

        // Logging Settings
        'logLevel' => 'error',         // Log level: 'debug', 'info', 'warning', 'error'


        // ========================================
        // QR CODE SETTINGS
        // ========================================
        // Appearance, styling, logo, and download options for QR codes

        // Note: Individual shortlinks inherit these defaults. Only custom-set values are saved.
        // If a shortlink's color matches the global default, it's stored as NULL and will
        // automatically update when you change the global default.
        'enableQrCodes' => true,
        'defaultQrSize' => 256,        // Size in pixels (100-1000)
        'defaultQrFormat' => 'png',    // Format: 'png' or 'svg'
        'defaultQrColor' => '#000000', // Foreground color (default: black)
        'defaultQrBgColor' => '#FFFFFF', // Background color (default: white)
        'defaultQrMargin' => 4,        // White space around QR code (0-10 modules)
        'qrModuleStyle' => 'square',   // Module shape: 'square', 'rounded', 'dots'
        'qrEyeStyle' => 'square',      // Eye shape: 'square', 'rounded', 'leaf'
        'qrEyeColor' => null,          // Eye color (null = use main color)

        // Logo Settings
        'enableQrLogo' => false,       // Enable logo overlay in center of QR codes
        // 'qrLogoVolumeUid' => null,  // Asset volume UID for logo selection (usually set in UI)
        // 'defaultQrLogoId' => null,  // Default logo asset ID (usually set in UI)
        'qrLogoSize' => 20,            // Logo size as percentage (10-30%)

        // Technical Options
        'defaultQrErrorCorrection' => 'M', // Error correction level: L, M, Q, H

        // Download Settings
        'enableQrDownload' => true,    // Allow users to download QR codes
        'qrDownloadFilename' => '{code}-qr-{size}', // Pattern with {code}, {size}, {format}


        // ========================================
        // ANALYTICS SETTINGS
        // ========================================
        // Click tracking, device detection, and data retention

        'enableAnalytics' => true,
        'enableGeoDetection' => false, // Detect visitor location for analytics
        'anonymizeIpAddress' => false, // Subnet masking (192.168.1.123 → 192.168.1.0) before hashing
        'analyticsRetention' => 90,    // Days to keep analytics data (0 = unlimited)


        // ========================================
        // REDIRECT BEHAVIOR SETTINGS
        // ========================================
        // How redirects behave and where they go

        'defaultHttpCode' => 301,      // Default HTTP redirect code (301, 302, 307, 308)
        'notFoundRedirectUrl' => '/',  // Where to redirect for invalid/disabled shortlinks


        // ========================================
        // INTEGRATION SETTINGS
        // ========================================
        // Third-party integrations for enhanced functionality

        'enabledIntegrations' => [],   // Enabled integration handles (e.g., ['seomatic', 'redirect-manager'])

        // SEOmatic Integration
        'seomaticTrackingEvents' => ['redirect', 'qr_scan'], // Event types to track
        'seomaticEventPrefix' => 'shortlink_manager', // Event prefix for GTM/GA events (lowercase, numbers, underscores only)

        // Redirect Manager Integration
        'redirectManagerEvents' => ['slug-change', 'expire', 'delete'], // Which events create redirects


        // ========================================
        // INTERFACE SETTINGS
        // ========================================
        // Control panel interface options

        'itemsPerPage' => 50,          // Number of shortlinks per page (10-500)


        // ========================================
        // CACHE SETTINGS
        // ========================================
        // Performance and caching configuration

        // QR Code Caching
        'enableQrCodeCache' => true,   // Cache generated QR codes
        'qrCodeCacheDuration' => 86400, // QR cache duration in seconds (24 hours)

        // Device Detection Caching
        'cacheDeviceDetection' => true, // Cache device detection results
        'deviceDetectionCacheDuration' => 3600, // Device detection cache in seconds (1 hour)
    ],

    // Dev environment settings
    'dev' => [
        'logLevel' => 'debug',         // More verbose logging in dev
        'analyticsRetention' => 30,    // Keep less data in dev
        'cacheDeviceDetection' => false, // No cache - testing
        'enableQrCodeCache' => false,  // No cache - see changes immediately
        'qrCodeCacheDuration' => 60,   // 1 minute - minimal cache if enabled
    ],

    // Staging environment settings
    'staging' => [
        'logLevel' => 'info',          // Moderate logging in staging
        'analyticsRetention' => 90,
        'cacheDeviceDetection' => true,
        'deviceDetectionCacheDuration' => 1800, // 30 minutes
        'qrCodeCacheDuration' => 3600, // 1 hour
    ],

    // Production environment settings
    'production' => [
        'logLevel' => 'error',         // Only errors in production
        'analyticsRetention' => 365,   // Keep more data in production
        'cacheDeviceDetection' => true,
        'deviceDetectionCacheDuration' => 7200, // 2 hours
        'qrCodeCacheDuration' => 604800, // 7 days - QR codes rarely change
    ],
];
