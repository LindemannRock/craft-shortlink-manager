<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services\analytics;

use lindemannrock\base\traits\GeoLookupTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\records\AnalyticsRecord;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\Request;

/**
 * Analytics Tracking Service
 *
 * Click tracking, IP hashing, anonymization, and geo data population.
 *
 * @author    LindemannRock
 * @package   ShortLinkManager
 * @since     5.0.0
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
     * @since 5.0.0
     */
    public function trackClick(ShortLink $shortLink, Request $request, string $source = 'direct'): void
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        if (!$settings->enableAnalytics) {
            return;
        }

        $record = new AnalyticsRecord();
        $record->linkId = $shortLink->id;
        $record->siteId = $shortLink->siteId;
        $record->destinationUrl = $shortLink->destinationUrl; // Capture destination at click time

        // Get IP address
        $ip = $request->getUserIP();

        // Step 1: Anonymize IP if enabled (subnet masking BEFORE hashing)
        if ($settings->anonymizeIpAddress && $ip) {
            $ip = $this->anonymizeIp($ip);
        }

        // Step 2: Hash IP with salt for storage
        if ($ip) {
            try {
                $record->ip = $this->hashIpWithSalt($ip);
            } catch (\Exception $e) {
                $this->logError('Failed to hash IP address', ['error' => $e->getMessage()]);
                $record->ip = null;
                $ip = null; // Prevent geo lookup with raw IP
            }
        } else {
            $record->ip = null;
        }

        // Step 3: Get geo location (uses anonymized or full IP, skipped if hash failed)
        if ($settings->enableGeoDetection && $ip) {
            $this->populateGeoData($record, $ip);
        }

        // Get user agent
        $record->userAgent = $request->getUserAgent();

        // Get referrer
        $record->referer = $request->getReferrer();

        // Detect device/browser info using Matomo DeviceDetector
        $deviceInfo = ShortLinkManager::$plugin->deviceDetection->detectDevice($record->userAgent);

        // Get language from device detection (includes fallback logic)
        $record->language = $deviceInfo['language'] ?? null;

        // Populate record with device detection data
        $record->deviceType = $deviceInfo['deviceType'];
        $record->deviceBrand = $deviceInfo['deviceBrand'];
        $record->deviceModel = $deviceInfo['deviceModel'];
        $record->browser = $deviceInfo['browser'];
        $record->browserVersion = $deviceInfo['browserVersion'];
        $record->browserEngine = $deviceInfo['browserEngine'];
        $record->osName = $deviceInfo['osName'];
        $record->osVersion = $deviceInfo['osVersion'];
        $record->clientType = $deviceInfo['clientType'];
        $record->isRobot = $deviceInfo['isRobot'];
        $record->isMobileApp = $deviceInfo['isMobileApp'];
        $record->botName = $deviceInfo['botName'];

        // Store source in metadata (like Smart Links does)
        $metadata = [
            'source' => $source,
        ];
        $record->metadata = json_encode($metadata);

        $record->save();
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
     * Populate geo data on analytics record from IP
     *
     * @param AnalyticsRecord $record
     * @param string $ip
     */
    private function populateGeoData(AnalyticsRecord $record, string $ip): void
    {
        $location = ShortLinkManager::$plugin->analytics->getLocationFromIp($ip);

        if ($location) {
            $record->country = $location['countryCode'];
            $record->city = $location['city'];
            $record->region = $location['region'];
            $record->latitude = $location['lat'];
            $record->longitude = $location['lon'];
        }
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
