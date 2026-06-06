<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests;

use Craft;
use lindemannrock\base\testing\IntegrationTestCase;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\services\AnalyticsService;
use lindemannrock\shortlinkmanager\services\ShortLinksService;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Base test case for shortlink-manager integration tests.
 *
 * Extends the shared {@see IntegrationTestCase} for component snapshot/restore
 * and generic Query helpers, and layers plugin-specific shorthand on top:
 *  - direct accessors for `shortLinks` / `analytics` services
 *  - per-test marker prefix + DB purge helpers for both the element table and
 *    its sibling analytics rows
 *  - {@see seedShortLink()} convenience for spinning up a saved element with
 *    a marker slug
 *  - {@see cleanupExternalState()} override that flushes Craft's cache so
 *    invalidated shortlink cache entries don't leak between tests (the plugin
 *    uses `Craft::$app->cache` for slug/id lookups)
 *
 * Subclasses can override `setUp()` for additional fixture work but should
 * call `parent::setUp()` to keep marker-based isolation working.
 *
 * @since 5.19.0
 */
abstract class TestCase extends IntegrationTestCase
{
    /**
     * Marker prefix used for every test-seeded shortlink slug. The
     * shortlinkmanager table has a UNIQUE index on `slug` and the analytics
     * table cascades on `linkId`, so deleting the element rows handles the
     * full cleanup chain via FK CASCADE.
     */
    protected const MARKER = 'sl-test-';

    protected ShortLinksService $shortLinks;
    protected AnalyticsService $analytics;

    /**
     * Slug + id pairs for every shortlink seeded in this test. Used by
     * {@see cleanupExternalState()} to invalidate the per-link Craft cache
     * entries (`shortlinkmanager_link_<id>`, `..._slug_<slug>`,
     * `..._code_<slug>`) that `ShortLinksService::cacheLink()` populates on
     * read. Without this purge, a fetch in the next test could return a
     * `null` cached miss for an id that no longer exists, or worse, a stale
     * object held by Yii's array-cache.
     *
     * @var list<array{id: int, slug: string}>
     */
    private array $seededLinks = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->shortLinks = ShortLinkManager::$plugin->shortLinks;
        $this->analytics = ShortLinkManager::$plugin->analytics;
        $this->purgeTestShortLinks();
    }

    protected function tearDown(): void
    {
        // Parent clears external cache state, deletes tracked elements, and
        // restores swapped components.
        parent::tearDown();
    }

    /**
     * Override hook called from `IntegrationTestCase::tearDown()` BEFORE
     * component restoration. Invalidate the per-id and per-slug cache
     * entries that `ShortLinksService` populates so the next test starts
     * with a clean lookup cache.
     */
    protected function cleanupExternalState(): void
    {
        foreach ($this->seededLinks as $link) {
            $this->shortLinks->invalidateShortLinkCache($link['id'], $link['slug']);
        }
        $this->seededLinks = [];
    }

    /**
     * Seed a saved {@see ShortLink} element with a marker code. The marker
     * pattern is `sl-test-link-<n>-<random>` so the element survives the unique
     * slug index and `purgeTestShortLinks()` can wipe it by LIKE prefix on
     * either `slug` or `code` (the two columns mirror each other for vanity
     * links, since the marker is already normalized).
     *
     * Built directly rather than via `createShortLink()` so we can pin both
     * `code` and `slug` to the marker — `createShortLink()` only forwards
     * to `slug`, leaving `code` for `beforeValidate()` to regenerate, which
     * would overwrite the marker.
     *
     * @param array<string, mixed> $overrides
     */
    protected function seedShortLink(array $overrides = []): ShortLink
    {
        $marker = str_replace('_', '-', $this->nextTestMarker(self::MARKER, 'link'));

        $element = new ShortLink();
        $element->code = $overrides['code'] ?? $marker;
        $element->slug = $overrides['slug'] ?? $marker;
        $element->linkType = $overrides['linkType'] ?? 'vanity';
        $element->shortLinkType = $overrides['shortLinkType'] ?? 'manual';
        $element->destinationUrl = $overrides['destinationUrl'] ?? 'https://example.com/test';
        $element->siteId = $overrides['siteId'] ?? Craft::$app->getSites()->getPrimarySite()->id;
        $element->httpCode = $overrides['httpCode'] ?? 302;
        $element->setEnabledForSite($overrides['enabled'] ?? true);

        $this->assertTrue(
            $this->shortLinks->saveShortLink($element),
            'Seeded shortlink must save — errors: ' . json_encode($element->getErrors()),
        );

        if ($element->id !== null) {
            $this->trackElementForCleanup((int) $element->id);
            $this->seededLinks[] = ['id' => (int) $element->id, 'slug' => (string) $element->slug];
        }

        return $element;
    }

    /**
     * Reload a shortlink from the DB and return the persisted `hits` count.
     * Bypasses any in-memory model caching the service might hold.
     */
    protected function fetchHitsFromDb(int $id): int
    {
        $row = $this->fetchRow('{{%shortlinkmanager}}', ['id' => $id]);
        $this->assertNotNull($row, "Shortlink row {$id} not found.");

        return (int) $row['hits'];
    }

    /**
     * Temporarily override plugin settings on the live settings model.
     *
     * @param array<string, mixed> $overrides
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function withSettings(array $overrides, callable $callback): mixed
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $previous = [];

        foreach ($overrides as $attribute => $value) {
            $previous[$attribute] = $settings->{$attribute};
            $settings->{$attribute} = $value;
        }

        try {
            return $callback();
        } finally {
            foreach ($previous as $attribute => $value) {
                $settings->{$attribute} = $value;
            }
        }
    }

    /**
     * DELETE FROM {%shortlinkmanager} WHERE slug LIKE 'sl-test-%' —
     * the FK CASCADE on the analytics + content + element tables drains
     * the rest. The slug column is the only place the marker is guaranteed
     * to appear; the element/content rows don't carry it.
     */
    protected function purgeTestShortLinks(): void
    {
        $rows = (new \craft\db\Query())
            ->from('{{%shortlinkmanager}}')
            ->where(['like', 'slug', self::MARKER . '%', false])
            ->select(['id'])
            ->column();

        if (empty($rows)) {
            return;
        }

        // Delete via the elements service so soft-deleted rows in {{%elements}}
        // are tracked correctly and indexes stay consistent. The FK CASCADE
        // from {{%shortlinkmanager}} → {{%elements}}.id then propagates the
        // delete through the rest of the plugin's tables.
        foreach ($rows as $id) {
            $element = ShortLink::find()->id((int) $id)->status(null)->one();
            if ($element !== null) {
                Craft::$app->elements->deleteElement($element, true);
            }
        }
    }
}
