<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use Craft;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use craft\web\UrlRule;
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
    public function testEveryCurrentSiteIdentifierRoutesAcrossThePublicRouteFamily(): void
    {
        $this->withSettings([
            'usePrefix' => true,
            'slugPrefix' => 's',
            'qrPrefix' => 's/qr',
        ], function(): void {
            $rules = $this->siteUrlRules();

            foreach ($this->currentSiteIdentifiers() as $identifier) {
                $this->assertRoutesTo(
                    $rules['priority'],
                    "{$identifier}/shortlink-manager/redirect/go/sl-test-route",
                    'shortlink-manager/redirect/go',
                    $identifier,
                );
                $this->assertRoutesTo(
                    $rules['priority'],
                    "{$identifier}/s/sl-test-route",
                    'shortlink-manager/redirect/index',
                    $identifier,
                );
                $this->assertRoutesTo(
                    $rules['priority'],
                    "{$identifier}/s/qr/sl-test-route",
                    'shortlink-manager/qr-code/generate',
                    $identifier,
                );
                $this->assertRoutesTo(
                    $rules['priority'],
                    "{$identifier}/s/qr/sl-test-route/view",
                    'shortlink-manager/qr-code/display',
                    $identifier,
                );
            }
        });

        $this->withSettings([
            'usePrefix' => true,
            'slugPrefix' => 'go',
            'qrPrefix' => 'qr',
        ], function(): void {
            $rules = $this->siteUrlRules();

            foreach ($this->currentSiteIdentifiers() as $identifier) {
                $this->assertRoutesTo(
                    $rules['priority'],
                    "{$identifier}/qr/sl-test-route",
                    'shortlink-manager/qr-code/generate',
                    $identifier,
                );
                $this->assertRoutesTo(
                    $rules['priority'],
                    "{$identifier}/qr/sl-test-route/view",
                    'shortlink-manager/qr-code/display',
                    $identifier,
                );
            }
        });
    }

    public function testSiteIdentifierPatternContainsOnlySafelyEscapedCurrentValues(): void
    {
        $rules = $this->siteUrlRules();
        $pattern = $this->siteIdentifierPattern($rules['priority']);
        $expected = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            foreach ([$site->handle, (string)$site->id, $site->uid] as $identifier) {
                $expected[] = preg_quote($identifier, '/');
            }
        }

        self::assertSame(array_values(array_unique($expected)), explode('|', $pattern));
    }

    public function testUnknownSiteIdentifiersAndReservedRootCodesDoNotMatchPluginRoutes(): void
    {
        $this->withSettings([
            'usePrefix' => false,
            'slugPrefix' => 's',
            'qrPrefix' => 's/qr',
            'reservedCodes' => ['admin', 'api'],
        ], function(): void {
            $rules = $this->siteUrlRules();
            $allRules = array_merge($rules['priority'], $rules['fallback']);
            $unknownIdentifiers = [
                '999999999',
                '00000000-0000-4000-8000-000000000000',
            ];

            foreach ($unknownIdentifiers as $identifier) {
                self::assertNull($this->matchRoute($allRules, "{$identifier}/s/sl-test-route"));
                self::assertNull($this->matchRoute($allRules, "{$identifier}/s/qr/sl-test-route"));
            }

            foreach ($this->currentSiteIdentifiers() as $identifier) {
                $this->assertRoutesTo(
                    $allRules,
                    "{$identifier}/sl-test-root-route",
                    'shortlink-manager/redirect/index',
                    $identifier,
                );
                self::assertNull($this->matchRoute($allRules, "{$identifier}/admin"));
                self::assertNull($this->matchRoute($allRules, "{$identifier}/api"));
            }
        });
    }

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
            self::assertArrayHasKey('shortlink-manager/redirect/go/<code:[a-zA-Z0-9\\-\\_]+>', $rules['priority']);
            self::assertArrayHasKey('<siteHandle:[^>]+>/shortlink-manager/redirect/go/<code:[a-zA-Z0-9\\-\\_]+>', $this->normalizedSiteHandleRules($rules['priority']));
            self::assertSame([], $rules['fallback']);
            foreach ($this->currentSiteIdentifiers() as $identifier) {
                self::assertNull($this->matchRoute($rules['priority'], "{$identifier}/sl-test-root-route"));
            }
        });

        $this->withSettings([
            'usePrefix' => false,
            'slugPrefix' => 's',
            'qrPrefix' => 's/qr',
            'reservedCodes' => ['admin', 'api'],
        ], function(): void {
            $rules = $this->siteUrlRules();

            self::assertArrayHasKey('s/<code:[a-zA-Z0-9\\-\\_]+>', $rules['priority']);
            self::assertArrayHasKey('shortlink-manager/redirect/go/<code:[a-zA-Z0-9\\-\\_]+>', $rules['priority']);
            self::assertArrayHasKey('<siteHandle:[^>]+>/shortlink-manager/redirect/go/<code:[a-zA-Z0-9\\-\\_]+>', $this->normalizedSiteHandleRules($rules['priority']));
            self::assertNotEmpty($rules['fallback']);
            self::assertArrayHasKey('<code:(?!(?i:admin|api)$)[a-zA-Z0-9\\-\\_]+>', $rules['fallback']);
            foreach ($this->currentSiteIdentifiers() as $identifier) {
                $this->assertRoutesTo(
                    $rules['fallback'],
                    "{$identifier}/sl-test-root-route",
                    'shortlink-manager/redirect/index',
                    $identifier,
                );
            }
        });
    }

    public function testPrefixAndSiteIdentifierCollisionsKeepTheConfiguredRouteOrder(): void
    {
        $siteHandle = Craft::$app->getSites()->getPrimarySite()->handle;
        $this->withSettings([
            'usePrefix' => true,
            'slugPrefix' => $siteHandle,
            'qrPrefix' => $siteHandle . '/qr',
        ], function() use ($siteHandle): void {
            $rules = $this->siteUrlRules();
            $redirect = $this->matchRoute($rules['priority'], "{$siteHandle}/sl-test-prefix-collision");
            self::assertNotNull($redirect);
            self::assertSame('shortlink-manager/redirect/index', $redirect['route']);
            self::assertArrayNotHasKey('siteHandle', $redirect['params']);

            $qr = $this->matchRoute($rules['priority'], "{$siteHandle}/qr/sl-test-prefix-collision");
            self::assertNotNull($qr);
            self::assertSame('shortlink-manager/qr-code/generate', $qr['route']);
            self::assertArrayNotHasKey('siteHandle', $qr['params']);
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

    /**
     * @param array<string, string> $rules
     * @return array<string, string>
     */
    private function normalizedSiteHandleRules(array $rules): array
    {
        $normalized = [];
        foreach ($rules as $pattern => $route) {
            $normalized[(string) preg_replace('/<siteHandle:[^>]+>/', '<siteHandle:[^>]+>', $pattern)] = $route;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function currentSiteIdentifiers(): array
    {
        $identifiers = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $identifiers[] = $site->handle;
            $identifiers[] = (string)$site->id;
            $identifiers[] = $site->uid;
        }

        return array_values(array_unique($identifiers));
    }

    /**
     * @param array<string, string> $rules
     */
    private function siteIdentifierPattern(array $rules): string
    {
        foreach (array_keys($rules) as $pattern) {
            if (preg_match('/^<siteHandle:([^>]+)>\/shortlink-manager\/redirect\/go\//', $pattern, $matches) === 1) {
                return $matches[1];
            }
        }

        self::fail('Site-aware go route was not registered.');
    }

    /**
     * @param array<string, string> $rules
     */
    private function assertRoutesTo(array $rules, string $path, string $expectedRoute, string $identifier): void
    {
        $match = $this->matchRoute($rules, $path);

        self::assertNotNull($match, "Expected {$path} to match a ShortLink route.");
        self::assertSame($expectedRoute, $match['route']);
        self::assertSame($identifier, $match['params']['siteHandle'] ?? null);
    }

    /**
     * @param array<string, string> $rules
     * @return array{route: string, params: array<string, string>}|null
     */
    private function matchRoute(array $rules, string $path): ?array
    {
        $manager = new UrlManager([
            'enablePrettyUrl' => true,
            'showScriptName' => false,
        ]);
        $request = new \yii\web\Request();
        $request->setPathInfo($path);

        foreach ($rules as $pattern => $route) {
            $match = (new UrlRule([
                'pattern' => $pattern,
                'route' => $route,
            ]))->parseRequest($manager, $request);
            if ($match !== false) {
                return [
                    'route' => $match[0],
                    'params' => $match[1],
                ];
            }
        }

        return null;
    }
}
