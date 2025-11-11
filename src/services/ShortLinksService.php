<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\records\ShortLinkRecord;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * ShortLinks Service
 */
class ShortLinksService extends Component
{
    use LoggingTrait;

    const CACHE_KEY = 'shortlinkmanager_link_';
    const CACHE_TAG = 'shortlinkmanager';

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('shortlink-manager');
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
        $element->slug = $options['code'] ?? $options['slug'] ?? '';
        $element->linkType = $options['type'] ?? $options['linkType'] ?? 'code';
        $element->destinationUrl = $options['url'] ?? $options['destinationUrl'] ?? $element->destinationUrl ?? '';
        $element->siteId = $options['siteId'] ?? $element->siteId ?? Craft::$app->getSites()->currentSite->id;
        $element->httpCode = $options['httpCode'] ?? $settings->defaultHttpCode ?? 301;
        $element->enabled = $options['enabled'] ?? true;
        $element->expiresAt = $options['expiresAt'] ?? null;
        $element->expiredRedirectUrl = $options['expiredRedirectUrl'] ?? null;

        // QR Code settings
        $element->qrCodeEnabled = $options['qrCodeEnabled'] ?? true;
        $element->qrCodeSize = $options['qrCodeSize'] ?? 256;
        $element->qrCodeColor = $options['qrCodeColor'] ?? '#000000';
        $element->qrCodeBgColor = $options['qrCodeBgColor'] ?? '#FFFFFF';
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
        $query = ShortLink::find()
            ->slug($slug)
            ->status(null); // Include all statuses (enabled, disabled, expired)

        if ($siteId) {
            $query->siteId($siteId);
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

        // Auto-create redirect if shortlink has traffic and settings enabled
        $this->handleDeletedShortLink($shortLink);

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
    public function generateUniqueSlug(int $length = 8): string
    {
        $maxAttempts = 100;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $slug = StringHelper::randomString($length);

            if ($this->validateSlug($slug)) {
                return $slug;
            }

            $attempt++;
        }

        // If we couldn't generate a unique slug, increase length
        return $this->generateUniqueSlug($length + 1);
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
                ['hits' => $shortLink->hits + 1],
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

        if ($shortLink && $element->getUrl() && $element->getUrl() !== $shortLink->destinationUrl) {
            $shortLink->destinationUrl = $element->getUrl();
            $this->saveShortLink($shortLink);

            $this->logInfo('Updated shortlink destination for element', [
                'elementId' => $element->id,
                'newUrl' => $shortLink->destinationUrl
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
                'slug' => $shortLink->slug
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
            // Clear QR code file cache only
            $cachePath = Craft::$app->path->getRuntimePath() . '/shortlink-manager/cache/qr/';
            $cleared = 0;
            if (is_dir($cachePath)) {
                $files = glob($cachePath . '*.cache');
                if ($files) {
                    foreach ($files as $file) {
                        if (is_file($file) && @unlink($file)) {
                            $cleared++;
                        }
                    }
                }
            }

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

        $slugPrefix = $settings->slugPrefix;
        $oldUrl = '/' . $slugPrefix . '/' . $oldSlug;
        $newUrl = '/' . $slugPrefix . '/' . $shortLink->slug;

        // Check if Redirect Manager integration is available and enabled
        $redirectIntegration = ShortLinkManager::$plugin->integration->getIntegration('redirect-manager');
        if (!$redirectIntegration || !$redirectIntegration->isAvailable() || !$redirectIntegration->isEnabled()) {
            $this->logDebug('Redirect Manager integration not available or not enabled');
            return;
        }

        // Get redirect manager plugin instance
        $redirectManager = Craft::$app->plugins->getPlugin('redirect-manager');
        if (!$redirectManager) {
            $this->logDebug('Redirect Manager plugin not found');
            return;
        }

        // SCENARIO 1: Try to handle undo
        try {
            $undoHandled = $redirectManager->redirects->handleUndoRedirect(
                $oldUrl,
                $newUrl,
                $shortLink->siteId,
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
            $success = $redirectManager->redirects->createRedirectRule([
                'sourceUrl' => $oldUrl,
                'sourceUrlParsed' => $oldUrl,
                'destinationUrl' => $newUrl,
                'matchType' => 'exact',
                'redirectSrcMatch' => 'pathonly',
                'statusCode' => 301,
                'siteId' => $shortLink->siteId,
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

    /**
     * Create redirect in Redirect Manager when shortlink expires
     *
     * @param ShortLink $shortLink
     * @return void
     */
    public function handleExpiredShortLink(ShortLink $shortLink): void
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        // Check if Redirect Manager integration is enabled
        $enabledIntegrations = $settings->enabledIntegrations ?? [];
        if (!in_array('redirect-manager', $enabledIntegrations)) {
            return;
        }

        // Check if expire event is enabled
        $redirectManagerEvents = $settings->redirectManagerEvents ?? [];
        if (!in_array('expire', $redirectManagerEvents)) {
            return;
        }

        // Only create redirect if there's an expiredRedirectUrl configured
        if (!$shortLink->expiredRedirectUrl) {
            return;
        }

        $slugPrefix = $settings->slugPrefix;
        $sourceUrl = '/' . $slugPrefix . '/' . $shortLink->slug;

        // Check if Redirect Manager integration is available and enabled
        $redirectIntegration = ShortLinkManager::$plugin->integration->getIntegration('redirect-manager');
        if (!$redirectIntegration || !$redirectIntegration->isAvailable() || !$redirectIntegration->isEnabled()) {
            $this->logDebug('Redirect Manager integration not available or not enabled');
            return;
        }

        // Get redirect manager plugin instance
        $redirectManager = Craft::$app->plugins->getPlugin('redirect-manager');
        if (!$redirectManager) {
            $this->logDebug('Redirect Manager plugin not found');
            return;
        }

        // Create the redirect
        try {
            $success = $redirectManager->redirects->createRedirectRule([
                'sourceUrl' => $sourceUrl,
                'sourceUrlParsed' => $sourceUrl,
                'destinationUrl' => $shortLink->expiredRedirectUrl,
                'matchType' => 'exact',
                'redirectSrcMatch' => 'pathonly',
                'statusCode' => 302,
                'siteId' => $shortLink->siteId,
                'enabled' => true,
                'priority' => 0,
                'creationType' => 'shortlink-expired',
                'sourcePlugin' => 'shortlink-manager',
            ]);

            if ($success) {
                $this->logInfo('Auto-created redirect for expired shortlink', [
                    'slug' => $shortLink->slug,
                    'sourceUrl' => $sourceUrl,
                    'destination' => $shortLink->expiredRedirectUrl,
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('Failed to create redirect rule', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Create redirect in Redirect Manager when shortlink is deleted
     *
     * @param ShortLink $shortLink
     * @return void
     */
    public function handleDeletedShortLink(ShortLink $shortLink): void
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        // Check if Redirect Manager integration is enabled
        $enabledIntegrations = $settings->enabledIntegrations ?? [];
        if (!in_array('redirect-manager', $enabledIntegrations)) {
            return;
        }

        // Check if delete event is enabled
        $redirectManagerEvents = $settings->redirectManagerEvents ?? [];
        if (!in_array('delete', $redirectManagerEvents)) {
            return;
        }

        // Only create redirect if shortlink has traffic
        if ($shortLink->hits === 0) {
            return;
        }

        $slugPrefix = $settings->slugPrefix;
        $sourceUrl = '/' . $slugPrefix . '/' . $shortLink->slug;
        $destinationUrl = $shortLink->expiredRedirectUrl ?? $settings->notFoundRedirectUrl ?? '/';

        // Check if Redirect Manager integration is available and enabled
        $redirectIntegration = ShortLinkManager::$plugin->integration->getIntegration('redirect-manager');
        if (!$redirectIntegration || !$redirectIntegration->isAvailable() || !$redirectIntegration->isEnabled()) {
            $this->logDebug('Redirect Manager integration not available or not enabled');
            return;
        }

        // Get redirect manager plugin instance
        $redirectManager = Craft::$app->plugins->getPlugin('redirect-manager');
        if (!$redirectManager) {
            $this->logDebug('Redirect Manager plugin not found');
            return;
        }

        // Create the redirect
        try {
            $success = $redirectManager->redirects->createRedirectRule([
                'sourceUrl' => $sourceUrl,
                'sourceUrlParsed' => $sourceUrl,
                'destinationUrl' => $destinationUrl,
                'matchType' => 'exact',
                'redirectSrcMatch' => 'pathonly',
                'statusCode' => 301,
                'siteId' => $shortLink->siteId,
                'enabled' => true,
                'priority' => 0,
                'creationType' => 'shortlink-deleted',
                'sourcePlugin' => 'shortlink-manager',
            ]);

            if ($success) {
                $this->logInfo('Auto-created redirect for deleted shortlink', [
                    'slug' => $shortLink->slug,
                    'sourceUrl' => $sourceUrl,
                    'hits' => $shortLink->hits,
                    'destination' => $destinationUrl,
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('Failed to create redirect rule', ['error' => $e->getMessage()]);
        }
    }
}
