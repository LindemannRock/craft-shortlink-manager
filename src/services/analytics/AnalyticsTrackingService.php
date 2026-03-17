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
 * @since     5.7.0
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

        // Get IP address
        $ip = $request->getUserIP();

        // Step 1: Anonymize IP if enabled (subnet masking BEFORE hashing)
        if ($settings->anonymizeIpAddress && $ip) {
            $ip = $this->anonymizeIp($ip);
        }

        // Step 2: Hash IP with salt for storage
        $ipHash = null;
        if ($ip) {
            try {
                $ipHash = $this->hashIpWithSalt($ip);
            } catch (\Exception $e) {
                $this->logError('Failed to hash IP address', ['error' => $e->getMessage()]);
                $ipHash = null;
                $ip = null; // Prevent geo lookup with raw IP
            }
        }

        // Get user agent
        $userAgent = $request->getUserAgent();

        // Get referrer
        $referer = $request->getReferrer();

        // Detect device/browser info using Matomo DeviceDetector
        $deviceInfo = ShortLinkManager::$plugin->deviceDetection->detectDevice($userAgent);

        // Get language from device detection (includes fallback logic)
        $location = null;
        if ($settings->enableGeoDetection && $ip) {
            $location = ShortLinkManager::$plugin->analytics->getLocationFromIp($ip);
            if ($location === null) {
                $this->logDebug('Geo lookup returned no data for shortlink analytics', [
                    'linkId' => $shortLink->id,
                    'siteId' => $shortLink->siteId,
                ]);
            }
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
     * Anonymize IP address (subnet masking)
     *
     * Masks IP addresses to reduce precision while maintaining subnet info for geo-location.
     * IPv4: Masks last octet (192.168.1.123 -> 192.168.1.0)
     * IPv6: Masks last 80 bits (keeps first 48 bits)
     *
     * @param string $ip The IP address to anonymize
     * @return string Anonymized IP address
     */
    private function anonymizeIp(string $ip): string
    {
        // IPv4: Mask last octet
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        // IPv6: Mask last 80 bits (keep first 48 bits)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $binary = inet_pton($ip);
            $anonymized = substr($binary, 0, 6) . str_repeat("\0", 10);
            return inet_ntop($anonymized);
        }

        return $ip;
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
