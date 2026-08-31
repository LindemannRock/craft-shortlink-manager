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
use craft\helpers\Json;
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

    /**
     * @var list<callable(): void>
     */
    private array $settingsOverrideRestorers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->shortLinks = ShortLinkManager::$plugin->shortLinks;
        $this->analytics = ShortLinkManager::$plugin->analytics;
        $this->purgeTestShortLinks();
    }

    protected function tearDown(): void
    {
        try {
            try {
                $this->restoreSettingsOverrides();
            } finally {
                // Parent clears external cache state, deletes tracked elements,
                // and restores swapped components.
                parent::tearDown();
            }
        } finally {
            // Also hard-delete any test shortlinks created as UNTRACKED side
            // effects — duplicates (duplicateElement) and field-managed
            // auto-creates aren't registered for cleanup. This outer finally is
            // intentional: cleanup must still run when settings restoration or
            // parent teardown fails.
            $this->purgeTestShortLinks();
        }
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

        $this->trackShortLinkForCleanup($element);

        return $element;
    }

    /**
     * Register any persisted ShortLink returned by a service/controller test.
     *
     * Generated-code tests cannot force the `sl-test-` marker without defeating
     * the behavior under test, so they must register the returned element
     * directly instead of relying on marker fallback cleanup.
     */
    protected function trackShortLinkForCleanup(ShortLink $element): void
    {
        if ($element->id === null) {
            return;
        }

        $this->trackElementForCleanup((int)$element->id);
        $this->seededLinks[] = ['id' => (int)$element->id, 'slug' => (string)$element->slug];
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
     * Normalize analytics metadata returned by raw queries or JSON-aware hydration.
     *
     * @return array<string, mixed>
     */
    protected function decodeAnalyticsMetadata(mixed $metadata): array
    {
        if (is_string($metadata)) {
            $metadata = Json::decode($metadata);
        }

        $this->assertIsArray($metadata, 'Analytics metadata must hydrate to a JSON object.');

        return $metadata;
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
        $restore = $this->createSettingsOverride($overrides);

        try {
            return $callback();
        } finally {
            $restore();
        }
    }

    /**
     * Apply a settings override for the rest of the current test.
     *
     * Use this from setUp() when every test in the class needs the same
     * override. The override is restored automatically in tearDown().
     *
     * @param array<string, mixed> $overrides
     */
    protected function applySettingsForTest(array $overrides): void
    {
        $this->settingsOverrideRestorers[] = $this->createSettingsOverride($overrides);
    }

    /**
     * Create a scoped override that beats both DB-backed settings and the
     * workspace's config/shortlink-manager.php file.
     *
     * The plugin intentionally ignores setSettings() and reapplies config-file
     * overrides on every getSettings() call, so tests must provide a temporary
     * config file rather than mutating only the in-memory model.
     *
     * @param array<string, mixed> $overrides
     * @return callable(): void
     */
    private function createSettingsOverride(array $overrides): callable
    {
        $config = Craft::$app->getConfig();
        $previousConfigDir = $config->configDir;
        $originalConfig = $config->getConfigFromFile('shortlink-manager');
        $testConfig = array_merge(is_array($originalConfig) ? $originalConfig : [], $overrides);

        $tempDir = Craft::$app->getPath()->getTempPath()
            . DIRECTORY_SEPARATOR
            . 'shortlink-manager-test-config-' . bin2hex(random_bytes(4));

        if (!is_dir($tempDir) && !mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            throw new \RuntimeException("Unable to create temporary config directory: {$tempDir}");
        }

        file_put_contents(
            $tempDir . DIRECTORY_SEPARATOR . 'shortlink-manager.php',
            "<?php\nreturn " . var_export($testConfig, true) . ";\n",
        );

        $settings = ShortLinkManager::$plugin->getSettings();
        $previous = [];

        foreach ($overrides as $attribute => $value) {
            $previous[$attribute] = $settings->{$attribute};
            $settings->{$attribute} = $value;
        }

        $config->configDir = $tempDir;
        \lindemannrock\base\helpers\DateFormatHelper::clearConfigCache('shortlink-manager');

        $restored = false;

        return static function() use (
            $config,
            $previousConfigDir,
            $settings,
            $previous,
            $tempDir,
            &$restored,
        ): void {
            if ($restored) {
                return;
            }

            $config->configDir = $previousConfigDir;

            foreach ($previous as $attribute => $value) {
                $settings->{$attribute} = $value;
            }

            \lindemannrock\base\helpers\DateFormatHelper::clearConfigCache('shortlink-manager');
            \craft\helpers\FileHelper::removeDirectory($tempDir);
            $restored = true;
        };
    }

    private function restoreSettingsOverrides(): void
    {
        while ($restore = array_pop($this->settingsOverrideRestorers)) {
            $restore();
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
            // trashed(null) includes soft-deleted elements: a test shortlink that
            // got soft-deleted (e.g. the field-delete tests, or a duplicate that
            // was trashed) still has a {{%shortlinkmanager}} row — and its slug
            // still occupies the unique index — until it is hard-deleted. Without
            // trashed(null), status(null)->one() returns null for those, the purge
            // skips them, and they accumulate across runs (also bumping unique-slug
            // suffixes, e.g. a stale sl-test-duplicate-2 forcing the next run to -3).
            $element = ShortLink::find()->id((int) $id)->status(null)->trashed(null)->one();
            if ($element !== null) {
                Craft::$app->elements->deleteElement($element, true);
            }
        }

        $remaining = (new \craft\db\Query())
            ->from('{{%shortlinkmanager}}')
            ->where(['like', 'slug', self::MARKER . '%', false])
            ->select(['id'])
            ->column();
        self::assertSame([], $remaining, 'Test-owned ShortLinks must not survive cleanup.');
    }
}
