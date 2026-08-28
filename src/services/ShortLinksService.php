<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\helpers\StringHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\helpers\SlugHandleHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\records\ShortLinkRecord;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\db\Expression;

/**
 * ShortLinks Service
 *
 * @since 5.0.0
 */
class ShortLinksService extends Component
{
    use LoggingTrait;

    /**
     * @var string Cache key prefix for individual shortlink lookups
     */
    public const CACHE_KEY = 'shortlinkmanager_link_';

    /**
     * @var string Cache tag used to tag all shortlink caches
     */
    public const CACHE_TAG = 'shortlinkmanager';

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
    }

    /**
     * Create a new shortlink
     *
     * @param array $options
     * @return ShortLink|null
     */
    public function createShortLink(array $options): ?ShortLink
    {
        $element = new ShortLink();
        $settings = ShortLinkManager::$plugin->getSettings();

        // Handle element-based shortlink
        if (isset($options['element'])) {
            $shortLinkedElement = $options['element'];
            $element->elementId = $shortLinkedElement->id;
            $element->elementType = get_class($shortLinkedElement);
            $element->siteId = $shortLinkedElement->siteId ?? Craft::$app->getSites()->currentSite->id;
            $element->destinationUrl = $shortLinkedElement->getUrl() ?? '';
        }

        // Set properties from options
        $element->slug = SlugHandleHelper::normalizeSlug($options['code'] ?? $options['slug'] ?? '', '');
        $element->linkType = $options['type'] ?? $options['linkType'] ?? 'code';
        $element->shortLinkType = $options['shortLinkType'] ?? 'manual';
        $element->destinationUrl = $options['url'] ?? $options['destinationUrl'] ?? $element->destinationUrl ?? '';
        $element->siteId = $options['siteId'] ?? $element->siteId ?? Craft::$app->getSites()->currentSite->id;
        $element->httpCode = $options['httpCode'] ?? $settings->defaultHttpCode ?? 302;
        $element->enabled = $options['enabled'] ?? true;
        $element->dateExpired = $options['dateExpired'] ?? $options['expiresAt'] ?? null;
        $element->expiredRedirectUrl = $options['expiredRedirectUrl'] ?? null;

        // QR Code settings
        $element->qrCodeEnabled = $options['qrCodeEnabled'] ?? true;
        $element->qrCodeSize = $options['qrCodeSize'] ?? max(100, min(1000, (int)$settings->defaultQrSize));
        $element->qrCodeColor = $options['qrCodeColor'] ?? null;
        $element->qrCodeBgColor = $options['qrCodeBgColor'] ?? null;
        $element->qrCodeEyeColor = $options['qrCodeEyeColor'] ?? null;
        $element->qrCodeFormat = $options['qrCodeFormat'] ?? null;
        $element->qrLogoId = $options['qrLogoId'] ?? null;

        // Generate slug if not provided (auto-generated links)
        if (empty($element->slug)) {
            if ($element->linkType === 'code') {
                $element->slug = $this->generateUniqueSlug($settings->codeLength ?? 8);
            } else {
                $this->logError('Slug is required for vanity URLs');
                return null;
            }
        }

        // Validate slug
        if (!$this->validateSlug($element->slug)) {
            $this->logError('Invalid or duplicate slug', ['slug' => $element->slug]);
            return null;
        }

        // Save the shortlink element
        if (Craft::$app->elements->saveElement($element)) {
            return $element;
        }

        if ($element->hasErrors()) {
            $this->logError('ShortLink validation failed', ['errors' => $element->getErrors()]);
        }

        return null;
    }

    /**
     * Get shortlink by ID
     *
     * @param int $id
     * @param int|null $siteId
     * @return ShortLink|null
     */
    public function getById(int $id, ?int $siteId = null): ?ShortLink
    {
        return ShortLink::find()
            ->id($id)
            ->siteId($siteId)
            ->one();
    }

    /**
     * Get shortlink by slug (code)
     *
     * @param string $slug
     * @param int|null $siteId
     * @return ShortLink|null
     */
    public function getBySlug(string $slug, ?int $siteId = null): ?ShortLink
    {
        $slug = SlugHandleHelper::normalizeSlug($slug, '');

        $query = ShortLink::find()
            ->slug($slug)
            ->status(null); // Include all statuses (enabled, disabled, expired)

        if ($siteId) {
            $query->siteId($siteId);
        } else {
            $query->siteId('*');
        }

        return $query->one();
    }

    /**
     * Backwards compatibility: Get shortlink by code
     *
     * @param string $code
     * @param int|null $siteId
     * @return ShortLink|null
     */
    public function getByCode(string $code, ?int $siteId = null): ?ShortLink
    {
        return $this->getBySlug($code, $siteId);
    }

    /**
     * Get shortlink by element
     *
     * @param ElementInterface $element
     * @param int|null $siteId
     * @return ShortLink|null
     */
    public function getByElement(ElementInterface $element, ?int $siteId = null): ?ShortLink
    {
        $siteId = $siteId ?? $element->siteId ?? Craft::$app->getSites()->currentSite->id;

        return ShortLink::find()
            ->elementId($element->id)
            ->siteId($siteId)
            ->status(null) // Include all statuses (enabled, disabled, expired, pending)
            ->one();
    }

    /**
     * Get all shortlinks
     *
     * @param array $criteria
     * @return array
     */
    public function getAll(array $criteria = []): array
    {
        $query = ShortLink::find();

        if (isset($criteria['siteId'])) {
            $query->siteId($criteria['siteId']);
        }

        if (isset($criteria['status'])) {
            $query->status($criteria['status']);
        }

        if (isset($criteria['linkType'])) {
            $query->linkType($criteria['linkType']);
        }

        if (isset($criteria['expired'])) {
            $query->expired($criteria['expired']);
        }

        if (isset($criteria['limit'])) {
            $query->limit($criteria['limit']);
        }

        if (isset($criteria['offset'])) {
            $query->offset($criteria['offset']);
        }

        $query->orderBy(['dateCreated' => SORT_DESC]);

        return $query->all();
    }

    /**
     * Save a shortlink
     *
     * @param ShortLink $element
     * @param bool $runValidation
     * @return bool
     */
    public function saveShortLink(ShortLink $element, bool $runValidation = true): bool
    {
        $oldSlug = null;

        // Get old slug if this is an update (element has an ID)
        if ($element->id) {
            $oldRecord = ShortLinkRecord::findOne($element->id);
            if ($oldRecord) {
                $oldSlug = $oldRecord->slug;
            }
        }

        // Save the element
        $success = Craft::$app->elements->saveElement($element, $runValidation);

        if ($success) {
            // Handle slug changes - create redirect from old to new
            if ($oldSlug && $oldSlug !== $element->slug) {
                $this->handleSlugChange($oldSlug, $element);
                ShortLinkManager::$plugin->servdStaticCache->purgeSlug($oldSlug);
            }

            // Invalidate cache
            $this->invalidateShortLinkCache($element->id, $element->slug);
        } else {
            if ($element->hasErrors()) {
                $this->logError('ShortLink validation failed', ['errors' => $element->getErrors()]);
            }
        }

        return $success;
    }

    /**
     * Delete a shortlink
     *
     * @param int $id
     * @return bool
     */
    public function deleteShortLink(int $id): bool
    {
        // Get the shortlink element before deleting
        $shortLink = $this->getById($id);

        if (!$shortLink) {
            return false;
        }

        // Delete the element
        $success = Craft::$app->elements->deleteElement($shortLink);

        if ($success) {
            $this->invalidateCaches();
        }

        return $success;
    }

    /**
     * Generate a unique slug
     *
     * @param int $length
     * @return string
     */
    public function generateUniqueSlug(int $length = 8, int $depth = 0): string
    {
        $maxAttempts = 100;
        $maxDepth = 4;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $slug = StringHelper::randomString($length);

            if ($this->validateSlug($slug)) {
                return $slug;
            }

            $attempt++;
        }

        // If we couldn't generate a unique slug, increase length (with depth limit)
        if ($depth < $maxDepth) {
            return $this->generateUniqueSlug($length + 1, $depth + 1);
        }

        // Final fallback: append timestamp to guarantee uniqueness
        return StringHelper::randomString($length) . '-' . time();
    }

    /**
     * Backwards compatibility: Generate unique code
     *
     * @param int $length
     * @return string
     */
    public function generateUniqueCode(int $length = 8): string
    {
        return $this->generateUniqueSlug($length);
    }

    /**
     * Validate a slug
     *
     * @param string $slug
     * @param int|null $excludeId
     * @return bool
     */
    public function validateSlug(string $slug, ?int $excludeId = null): bool
    {
        $slug = SlugHandleHelper::normalizeSlug($slug, '');
        if ($slug === '') {
            return false;
        }

        // Check if reserved
        if ($this->isReservedSlug($slug)) {
            return false;
        }

        // Check uniqueness
        $query = (new Query())
            ->from('{{%shortlinkmanager}}')
            ->where(['slug' => $slug]);

        if ($excludeId) {
            $query->andWhere(['!=', 'id', $excludeId]);
        }

        return !$query->exists();
    }

    /**
     * Backwards compatibility: Validate code
     *
     * @param string $code
     * @param int|null $excludeId
     * @return bool
     */
    public function validateCode(string $code, ?int $excludeId = null): bool
    {
        return $this->validateSlug($code, $excludeId);
    }

    /**
     * Check if slug is reserved
     *
     * @param string $slug
     * @return bool
     */
    public function isReservedSlug(string $slug): bool
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        return in_array(strtolower($slug), array_map('strtolower', $settings->reservedCodes ?? []));
    }

    /**
     * Backwards compatibility: Check if code is reserved
     *
     * @param string $code
     * @return bool
     */
    public function isReservedCode(string $code): bool
    {
        return $this->isReservedSlug($code);
    }

    /**
     * Increment hit counter
     *
     * @param ShortLink $shortLink
     * @return void
     */
    public function incrementHits(ShortLink $shortLink): void
    {
        // Update directly in database to avoid triggering full element save
        Craft::$app->db->createCommand()
            ->update(
                '{{%shortlinkmanager}}',
                ['hits' => new Expression('[[hits]] + 1')],
                ['id' => $shortLink->id]
            )
            ->execute();

        // Update the model
        $shortLink->hits++;

        // Invalidate cache
        $this->invalidateShortLinkCache($shortLink->id, $shortLink->slug);
    }

    /**
     * Handle element save event
     *
     * @param ElementInterface $element
     * @return void
     */
    public function onSaveElement(ElementInterface $element): void
    {
        $shortLink = $this->getByElement($element);

        $elementUrl = $element->getUrl();
        if ($shortLink && $elementUrl && $elementUrl !== $shortLink->destinationUrl) {
            $oldUrl = $shortLink->destinationUrl;
            $shortLink->destinationUrl = $elementUrl;

            if (!$this->saveShortLink($shortLink)) {
                return;
            }

            $this->logDebug('Updated shortlink destination for element', [
                'shortLinkId' => $shortLink->id,
                'elementId' => $element->id,
                'siteId' => $element->siteId ?? null,
                'oldUrl' => $oldUrl,
                'newUrl' => $shortLink->destinationUrl,
            ]);
        }
    }

    /**
     * Handle element delete event
     *
     * @param ElementInterface $element
     * @return void
     */
    public function onDeleteElement(ElementInterface $element): void
    {
        $shortLink = $this->getByElement($element);

        if ($shortLink) {
            $this->deleteShortLink($shortLink->id);

            $this->logInfo('Deleted shortlink for element', [
                'elementId' => $element->id,
                'slug' => $shortLink->slug,
            ]);
        }
    }

    /**
     * Invalidate cache for a specific shortlink
     *
     * @param int $id
     * @param string|null $slug
     * @return void
     */
    public function invalidateShortLinkCache(int $id, ?string $slug = null): void
    {
        // Clear both ID-based and slug-based cache keys
        $idCacheKey = self::CACHE_KEY . $id;
        Craft::$app->getCache()->delete($idCacheKey);

        if ($slug) {
            $slugCacheKey = self::CACHE_KEY . 'slug_' . $slug;
            Craft::$app->getCache()->delete($slugCacheKey);

            // Also clear old 'code' cache key for backwards compatibility
            $codeCacheKey = self::CACHE_KEY . 'code_' . $slug;
            Craft::$app->getCache()->delete($codeCacheKey);
        }
    }

    /**
     * Invalidate ALL caches (used by utilities/clear cache)
     *
     * @return void
     */
    public function invalidateCaches(): void
    {
        try {
            $cleared = ShortLinkManager::$plugin->localCache->clearAllCaches();
            $this->logInfo('Invalidated ShortLink Manager caches', ['cleared' => $cleared]);
        } catch (\Exception $e) {
            $this->logError('Failed to invalidate caches', ['error' => $e->getMessage()]);
            // Don't throw - just log the error so cache clearing doesn't break
        }
    }

    /**
     * Handle shortlink slug change - create redirect from old to new
     *
     * @param string $oldSlug
     * @param ShortLink $shortLink
     * @return void
     */
    private function handleSlugChange(string $oldSlug, ShortLink $shortLink): void
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        // Check if Redirect Manager integration is enabled
        $enabledIntegrations = $settings->enabledIntegrations ?? [];
        if (!in_array('redirect-manager', $enabledIntegrations)) {
            return;
        }

        // Check if slug change event is enabled
        $redirectManagerEvents = $settings->redirectManagerEvents ?? [];
        if (!in_array('slug-change', $redirectManagerEvents)) {
            return;
        }

        $slugPrefix = trim((string) ($settings->slugPrefix ?? 's'), '/');
        $slugPrefix = $slugPrefix !== '' ? $slugPrefix : 's';
        $usePrefix = (bool) ($settings->usePrefix ?? true);

        $oldUrl = $usePrefix ? '/' . $slugPrefix . '/' . $oldSlug : '/' . $oldSlug;
        $newUrl = $usePrefix ? '/' . $slugPrefix . '/' . $shortLink->slug : '/' . $shortLink->slug;

        // Check if Redirect Manager integration is available and enabled
        $redirectIntegration = ShortLinkManager::$plugin->integration->getIntegration('redirect-manager');
        if (!$redirectIntegration || !$redirectIntegration->isAvailable() || !$redirectIntegration->isEnabled()) {
            $this->logDebug('Redirect Manager integration not available or not enabled');
            return;
        }

        // Get redirect manager plugin instance
        $redirectManager = PluginHelper::getPlugin('redirect-manager');
        if (!$redirectManager instanceof \lindemannrock\redirectmanager\RedirectManager) {
            $this->logDebug('Redirect Manager plugin not found');
            return;
        }

        // SCENARIO 1: Try to handle undo
        try {
            $undoHandled = $redirectManager->redirects->handleUndoRedirect(
                $oldUrl,
                $newUrl,
                null, // Shortlink slugs are shared across all sites
                'shortlink-slug-change',
                'shortlink-manager'
            );

            if ($undoHandled) {
                return; // Undo was handled
            }
        } catch (\Exception $e) {
            $this->logWarning('Failed to handle undo redirect', ['error' => $e->getMessage()]);
        }

        // SCENARIO 2: Create the redirect
        try {
            $success = $redirectManager->redirects->createRedirect([
                'sourceUrl' => $oldUrl,
                'sourceUrlParsed' => $oldUrl,
                'destinationUrl' => $newUrl,
                'matchType' => 'exact',
                'redirectSrcMatch' => 'pathonly',
                'statusCode' => 301,
                'siteId' => null, // Shortlink slugs are shared across all sites
                'enabled' => true,
                'priority' => 0,
                'creationType' => 'shortlink-slug-change',
                'sourcePlugin' => 'shortlink-manager',
            ], true); // Show notification

            if ($success) {
                $this->logInfo('Created redirect for slug change', [
                    'oldSlug' => $oldSlug,
                    'newSlug' => $shortLink->slug,
                    'oldUrl' => $oldUrl,
                    'newUrl' => $newUrl,
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('Failed to create redirect rule', ['error' => $e->getMessage()]);
        }
    }
}
