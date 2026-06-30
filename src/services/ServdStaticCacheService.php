<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services;

use craft\base\Component;
use craft\db\Query;
use craft\helpers\Queue;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Queues Servd static-cache purges for public shortlink URLs when Servd is present.
 *
 * @since 5.25.0
 */
class ServdStaticCacheService extends Component
{
    use LoggingTrait;

    private const PURGE_URL_BATCH_SIZE = 500;
    private const SERVD_PLUGIN_HANDLE = 'servd-asset-storage';
    private const PURGE_URLS_JOB = 'servd\\AssetStorage\\StaticCache\\Jobs\\PurgeUrlsJob';
    private const STATIC_CACHE = 'servd\\AssetStorage\\StaticCache\\StaticCache';

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
    }

    /**
     * Return whether the Servd static-cache purge API can be used.
     */
    public function isAvailable(): bool
    {
        return PluginHelper::isPluginEnabled(self::SERVD_PLUGIN_HANDLE)
            && class_exists(self::PURGE_URLS_JOB)
            && class_exists(self::STATIC_CACHE);
    }

    /**
     * Queue purges for the public URLs for a shortlink's current slug.
     */
    public function purgeShortLink(ShortLink $shortLink): void
    {
        $this->purgeUrls($this->urlsForShortLink($shortLink));
    }

    /**
     * Queue purges for the public URLs for a previously-used shortlink slug.
     */
    public function purgeShortLinkSlug(string $slug): void
    {
        $this->purgeUrls($this->urlsForSlug($slug));
    }

    /**
     * Queue purges for every public shortlink URL known to the plugin.
     */
    public function purgeAllShortLinks(): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        $urls = [];

        foreach ($this->eachSlug() as $slug) {
            array_push($urls, ...$this->urlsForSlug($slug));

            while (count($urls) >= self::PURGE_URL_BATCH_SIZE) {
                $this->purgeUrls(array_splice($urls, 0, self::PURGE_URL_BATCH_SIZE));
            }
        }

        if ($urls !== []) {
            $this->purgeUrls($urls);
        }
    }

    /**
     * Return public URLs that can become stale for a shortlink.
     *
     * @return list<string>
     */
    public function urlsForShortLink(ShortLink $shortLink): array
    {
        return $this->urlsForSlug((string)$shortLink->slug);
    }

    /**
     * Return public URLs that can become stale for a shortlink slug.
     *
     * @return list<string>
     */
    public function urlsForSlug(string $slug): array
    {
        $slug = trim($slug, '/');
        if ($slug === '') {
            return [];
        }

        $settings = ShortLinkManager::$plugin->getSettings();
        $urls = [];

        foreach ($settings->getEnabledSiteIds() as $siteId) {
            $siteId = (int)$siteId;
            foreach ($this->pathsForSlug($slug) as $path) {
                $urls[] = $settings->buildPublicUrl($path, $siteId);
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Queue a Servd URL purge job if Servd is installed and enabled.
     *
     * @param list<string> $urls
     */
    public function purgeUrls(array $urls): void
    {
        $urls = array_values(array_unique(array_filter($urls)));
        if ($urls === [] || !$this->isAvailable()) {
            return;
        }

        try {
            /** @var class-string $jobClass */
            $jobClass = self::PURGE_URLS_JOB;
            $job = new $jobClass(['urls' => $urls]);

            $staticCacheClass = self::STATIC_CACHE;
            Queue::push($job, $staticCacheClass::purgePriority());
        } catch (\Throwable $e) {
            $this->logWarning('Failed to queue Servd static-cache URL purge', [
                'error' => $e->getMessage(),
                'urls' => $urls,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function pathsForSlug(string $slug): array
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        $slugPrefix = trim((string)($settings->slugPrefix ?? 's'), '/');
        $slugPrefix = $slugPrefix !== '' ? $slugPrefix : 's';
        $qrPrefix = trim((string)($settings->qrPrefix ?? 'qr'), '/');
        $qrPrefix = $qrPrefix !== '' ? $qrPrefix : 'qr';
        $usePrefix = (bool)($settings->usePrefix ?? true);

        $shortlinkPath = $usePrefix ? $slugPrefix . '/' . $slug : $slug;

        return [
            $shortlinkPath,
            $qrPrefix . '/' . $slug . '/view',
        ];
    }

    /**
     * @return \Generator<string>
     */
    private function eachSlug(): \Generator
    {
        $query = (new Query())
            ->select(['slug'])
            ->distinct()
            ->from('{{%shortlinkmanager}}')
            ->where(['not', ['slug' => null]])
            ->orderBy(['slug' => SORT_ASC]);

        foreach ($query->batch(500) as $rows) {
            $seen = [];

            foreach ($rows as $row) {
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug === '' || isset($seen[$slug])) {
                    continue;
                }

                $seen[$slug] = true;
                yield $slug;
            }
        }
    }
}
