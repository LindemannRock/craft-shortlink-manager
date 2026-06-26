<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins local/private IP geo fallback behavior.
 *
 * @since 5.20.0
 */
final class AnalyticsGeoDefaultsTest extends TestCase
{
    public function testPrivateIpHasNoGeoLocationWithoutExplicitDefaults(): void
    {
        $this->withoutDefaultLocationEnv(function(): void {
            $this->withSettings([
                'defaultCountry' => null,
                'defaultCity' => null,
            ], function(): void {
                self::assertNull(
                    $this->analytics->getLocationFromIp('127.0.0.1'),
                    'Private/local IPs must not synthesize a default geo location unless both defaults are configured.',
                );
            });
        });
    }

    public function testPrivateIpUsesExplicitSupportedDefaults(): void
    {
        $this->withSettings([
            'defaultCountry' => 'US',
            'defaultCity' => 'New York',
        ], function(): void {
            $location = $this->analytics->getLocationFromIp('192.168.1.42');

            self::assertIsArray($location);
            self::assertSame('US', $location['countryCode']);
            self::assertSame('New York', $location['city']);
        });
    }

    public function testPrivateIpHasNoGeoLocationForUnsupportedDefaults(): void
    {
        $this->withSettings([
            'defaultCountry' => 'ZZ',
            'defaultCity' => 'Missing City',
        ], function(): void {
            self::assertNull(
                $this->analytics->getLocationFromIp('10.0.0.10'),
                'Unsupported local/private IP geo defaults should leave geo fields empty instead of falling back to Dubai.',
            );
        });
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withoutDefaultLocationEnv(callable $callback): mixed
    {
        $countryServer = $_SERVER['SHORTLINK_MANAGER_DEFAULT_COUNTRY'] ?? null;
        $cityServer = $_SERVER['SHORTLINK_MANAGER_DEFAULT_CITY'] ?? null;
        $countryEnv = getenv('SHORTLINK_MANAGER_DEFAULT_COUNTRY');
        $cityEnv = getenv('SHORTLINK_MANAGER_DEFAULT_CITY');

        unset($_SERVER['SHORTLINK_MANAGER_DEFAULT_COUNTRY'], $_SERVER['SHORTLINK_MANAGER_DEFAULT_CITY']);
        putenv('SHORTLINK_MANAGER_DEFAULT_COUNTRY');
        putenv('SHORTLINK_MANAGER_DEFAULT_CITY');

        try {
            return $callback();
        } finally {
            if ($countryServer !== null) {
                $_SERVER['SHORTLINK_MANAGER_DEFAULT_COUNTRY'] = $countryServer;
            }
            if ($cityServer !== null) {
                $_SERVER['SHORTLINK_MANAGER_DEFAULT_CITY'] = $cityServer;
            }
            if ($countryEnv !== false) {
                putenv('SHORTLINK_MANAGER_DEFAULT_COUNTRY=' . $countryEnv);
            }
            if ($cityEnv !== false) {
                putenv('SHORTLINK_MANAGER_DEFAULT_CITY=' . $cityEnv);
            }
        }
    }
}
