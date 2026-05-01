<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services\analytics;

use Craft;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use lindemannrock\base\helpers\AnalyticsIpHelper;
use lindemannrock\base\traits\GeoLookupTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\Request;

/**
 * Analytics Tracking Service
 *
 * Click tracking, IP hashing, anonymization, and geo data population.
 *
 * @author    LindemannRock
 * @package   ShortLinkManager
 * @since     5.13.0
 */
class AnalyticsTrackingService
{
    use LoggingTrait;
    use GeoLookupTrait;

    public function __construct()
    {
        $this->setLoggingHandle('shortlink-manager');
    }

    /**
     * Track a click
     *
     * @param ShortLink $shortLink
     * @param Request $request
     * @param string $source Source of the click (qr, direct, etc.)
     */
    public function trackClick(ShortLink $shortLink, Request $request, string $source = 'direct'): void
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        if (!$settings->enableAnalytics) {
            return;
        }

        $db = Craft::$app->getDb();

        $ipState = AnalyticsIpHelper::prepare(
            $request->getUserIP(),
            $settings->anonymizeIpAddress,
            $settings->enableGeoDetection,
            fn(string $ip): string => $this->hashIpWithSalt($ip),
        );

        if ($ipState['hashError'] !== null) {
            $this->logError('Failed to hash IP address', ['error' => $ipState['hashError']->getMessage()]);
        }

        $ipHash = $ipState['hashedIp'];
        $geoLookupIp = $ipState['geoLookupIp'];

        // Get user agent
        $userAgent = $request->getUserAgent();

        // Get referrer
        $referer = $request->getReferrer();

        // Detect device/browser info using Matomo DeviceDetector
        $deviceInfo = ShortLinkManager::$plugin->deviceDetection->detectDevice($userAgent);

        // Get language from device detection (includes fallback logic)
        $location = null;
        if ($geoLookupIp) {
            $location = ShortLinkManager::$plugin->analytics->getLocationFromIp($geoLookupIp);
        }

        // Store source in metadata (like Smart Links does)
        $metadata = [
            'source' => $source,
        ];

        $data = [
            'linkId' => $shortLink->id,
            'siteId' => $shortLink->siteId,
            'destinationUrl' => $shortLink->destinationUrl,
            'ip' => $ipHash,
            'userAgent' => $userAgent,
            'referer' => $referer,
            'language' => $deviceInfo['language'] ?? null,
            'deviceType' => $deviceInfo['deviceType'],
            'deviceBrand' => $deviceInfo['deviceBrand'],
            'deviceModel' => $deviceInfo['deviceModel'],
            'browser' => $deviceInfo['browser'],
            'browserVersion' => $deviceInfo['browserVersion'],
            'browserEngine' => $deviceInfo['browserEngine'],
            'osName' => $deviceInfo['osName'],
            'osVersion' => $deviceInfo['osVersion'],
            'clientType' => $deviceInfo['clientType'],
            'isRobot' => $deviceInfo['isRobot'],
            'isMobileApp' => $deviceInfo['isMobileApp'],
            'botName' => $deviceInfo['botName'],
            'country' => $location['countryCode'] ?? null,
            'city' => $location['city'] ?? null,
            'region' => $location['region'] ?? null,
            'latitude' => $location['lat'] ?? null,
            'longitude' => $location['lon'] ?? null,
            'metadata' => Json::encode($metadata),
            'dateCreated' => Db::prepareDateForDb(new \DateTime()),
            'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
            'uid' => StringHelper::UUID(),
        ];

        try {
            $db->createCommand()
                ->insert('{{%shortlinkmanager_analytics}}', $data)
                ->execute();
        } catch (\Throwable $e) {
            $this->logError('Failed to save shortlink analytics', [
                'linkId' => $shortLink->id,
                'siteId' => $shortLink->siteId,
                'error' => $e->getMessage(),
                'hasGeoData' => $location !== null,
                'country' => $data['country'],
                'city' => $data['city'],
            ]);
        }
    }

    /**
     * Hash IP address with salt for privacy
     *
     * Uses SHA256 with a secret salt to hash IPs. This prevents rainbow table attacks
     * while still allowing unique visitor tracking (same IP = same hash).
     *
     * @param string $ip The IP address to hash
     * @return string Hashed IP address (64 characters)
     * @throws \Exception If salt is not configured
     */
    private function hashIpWithSalt(string $ip): string
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $salt = $settings->ipHashSalt;

        if (!$salt || $salt === '$SHORTLINK_MANAGER_IP_SALT' || trim($salt) === '') {
            $this->logError('IP hash salt not configured - analytics tracking disabled', [
                'ip' => 'hidden',
                'saltValue' => $salt ?? 'NULL',
            ]);
            throw new \Exception('IP hash salt not configured. Run: php craft shortlink-manager/security/generate-salt');
        }

        return hash('sha256', $ip . $salt);
    }

    /**
     * Get geo lookup configuration from plugin settings
     *
     * @return array<string, mixed>
     */
    protected function getGeoConfig(): array
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        return [
            'provider' => $settings->geoProvider ?? 'ip-api.com',
            'apiKey' => $settings->geoApiKey ?? null,
        ];
    }
}
