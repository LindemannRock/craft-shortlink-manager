<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use craft\events\RegisterUrlRulesEvent;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins site route registration for prefixed URLs, optional root URLs, and
 * reserved-code exclusions. Covers the GH #44 route-precedence regression.
 *
 * @since 5.20.0
 */
final class SiteRouteRulesTest extends TestCase
{
    public function testRootFallbackRoutesAreOnlyPresentWhenPrefixIsDisabled(): void
    {
        $this->withSettings([
            'usePrefix' => true,
            'slugPrefix' => 's',
            'qrPrefix' => 's/qr',
            'reservedCodes' => ['admin', 'api'],
        ], function(): void {
            $rules = $this->siteUrlRules();

            self::assertArrayHasKey('s/<code:[a-zA-Z0-9\\-\\_]+>', $rules['priority']);
            self::assertSame([], $rules['fallback']);
        });

        $this->withSettings([
            'usePrefix' => false,
            'slugPrefix' => 's',
            'qrPrefix' => 's/qr',
            'reservedCodes' => ['admin', 'api'],
        ], function(): void {
            $rules = $this->siteUrlRules();

            self::assertArrayHasKey('s/<code:[a-zA-Z0-9\\-\\_]+>', $rules['priority']);
            self::assertNotEmpty($rules['fallback']);
            self::assertArrayHasKey('<code:(?!(?i:admin|api)$)[a-zA-Z0-9\\-\\_]+>', $rules['fallback']);
        });
    }

    public function testRouteMergeKeepsRootRoutesBehindExistingSiteRoutes(): void
    {
        $this->withSettings([
            'usePrefix' => false,
            'slugPrefix' => 's',
            'qrPrefix' => 's/qr',
            'reservedCodes' => ['api'],
        ], function(): void {
            $event = new RegisterUrlRulesEvent();
            $event->rules = [
                'api' => 'graphql/api',
                'about' => 'site/about',
            ];

            $siteUrlRules = $this->siteUrlRules();
            $event->rules = array_merge(
                $siteUrlRules['priority'],
                $event->rules,
                $siteUrlRules['fallback'],
            );

            $keys = array_keys($event->rules);
            $apiIndex = array_search('api', $keys, true);
            $rootFallbackIndex = array_search('<code:(?!(?i:api)$)[a-zA-Z0-9\\-\\_]+>', $keys, true);

            self::assertIsInt($apiIndex);
            self::assertIsInt($rootFallbackIndex);
            self::assertLessThan($rootFallbackIndex, $apiIndex);
            self::assertSame('graphql/api', $event->rules['api']);
        });
    }

    /**
     * @return array{priority: array<string, string>, fallback: array<string, string>}
     */
    private function siteUrlRules(): array
    {
        $method = new \ReflectionMethod(ShortLinkManager::$plugin, 'getSiteUrlRules');
        $method->setAccessible(true);

        /** @var array{priority: array<string, string>, fallback: array<string, string>} */
        return $method->invoke(ShortLinkManager::$plugin);
    }
}
