<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\controllers;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;
use craft\web\Controller;
use lindemannrock\base\helpers\AssetVolumeHelper;
use lindemannrock\base\helpers\CpNavHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\Response;

/**
 * Shortlinks Controller
 *
 * @since 5.0.0
 */
class ShortlinksController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
    }

    /**
     * List all links (element index)
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $user = Craft::$app->getUser();
        $settings = ShortLinkManager::$plugin->getSettings();

        // If user doesn't have manageLinks permission, redirect to first accessible section
        if (!$user->checkPermission('shortLinkManager:manageLinks')) {
            $sections = ShortLinkManager::$plugin->getCpSections($settings, false, true);
            $route = CpNavHelper::firstAccessibleRoute($user, $settings, $sections);
            if ($route) {
                return $this->redirect($route);
            }
            // No access at all
            $this->requirePermission('shortLinkManager:manageLinks');
        }

        // Get current site from request or Craft's current site
        $siteHandle = $this->request->getParam('site');
        $currentSite = $siteHandle
            ? Craft::$app->getSites()->getSiteByHandle($siteHandle)
            : null;

        // Fallback to current site if handle is invalid
        if (!$currentSite) {
            $currentSite = Craft::$app->getSites()->getCurrentSite();
        }

        // If current site is not enabled or user can't edit it, redirect to first accessible site
        $enabledSites = ShortLinkManager::$plugin->getEnabledSites();
        $enabledSiteIds = array_map(fn($s) => $s->id, $enabledSites);

        if (!in_array($currentSite->id, $enabledSiteIds)) {
            $firstSite = reset($enabledSites);
            if ($firstSite) {
                return $this->redirect('shortlink-manager?site=' . $firstSite->handle);
            }
        }

        // Enforce site edit permission (multi-site only)
        if (Craft::$app->getIsMultiSite()) {
            $this->requirePermission('editSite:' . $currentSite->uid);
        }

        return $this->renderTemplate('shortlink-manager/shortlinks/index');
    }

    /**
     * Edit a link
     *
     * @param int|null $shortLinkId
     * @param ShortLink|null $shortLink
     * @return Response
     */
    public function actionEdit(?int $shortLinkId = null, ?ShortLink $shortLink = null): Response
    {
        if ($shortLinkId) {
            $this->requirePermission('shortLinkManager:editLinks');

            if (!$shortLink) {
                // Get site from request or use current site
                $siteHandle = $this->request->getParam('site');
                $site = ($siteHandle ? Craft::$app->getSites()->getSiteByHandle($siteHandle) : null) ?? Craft::$app->getSites()->getCurrentSite();

                // Check if ShortLink Manager is enabled for this site
                $settings = ShortLinkManager::getInstance()->getSettings();
                if (!$settings->isSiteEnabled($site->id)) {
                    throw new \yii\web\ForbiddenHttpException(Craft::t('shortlink-manager', '{pluginName} is not enabled for this site.', ['pluginName' => ShortLinkManager::$plugin->getSettings()->getFullName()]));
                }

                // Enforce site edit permission (multi-site only)
                if (Craft::$app->getIsMultiSite()) {
                    $this->requirePermission('editSite:' . $site->uid);
                }

                $shortLink = ShortLink::find()
                    ->id($shortLinkId)
                    ->siteId($site->id)
                    ->status(null)
                    ->one();

                if (!$shortLink) {
                    throw new \yii\web\NotFoundHttpException(Craft::t('shortlink-manager', 'ShortLink not found'));
                }
            }

            $title = $shortLink->code ?? $shortLink->slug;
        } else {
            $this->requirePermission('shortLinkManager:createLinks');

            if (!$shortLink) {
                // Get site from request or use current site
                $siteHandle = $this->request->getParam('site');
                $site = ($siteHandle ? Craft::$app->getSites()->getSiteByHandle($siteHandle) : null) ?? Craft::$app->getSites()->getCurrentSite();

                // Check if ShortLink Manager is enabled for this site
                $settings = ShortLinkManager::getInstance()->getSettings();
                if (!$settings->isSiteEnabled($site->id)) {
                    throw new \yii\web\ForbiddenHttpException(Craft::t('shortlink-manager', '{pluginName} is not enabled for this site.', ['pluginName' => ShortLinkManager::$plugin->getSettings()->getFullName()]));
                }

                // Enforce site edit permission (multi-site only)
                if (Craft::$app->getIsMultiSite()) {
                    $this->requirePermission('editSite:' . $site->uid);
                }

                $shortLink = new ShortLink();
                $shortLink->siteId = $site->id;
                $shortLink->enabled = true;
                $shortLink->httpCode = ShortLinkManager::$plugin->getSettings()->defaultHttpCode ?? 302;
                $shortLink->linkType = 'code'; // Default to auto-generated
            }

            $title = Craft::t('shortlink-manager', 'Create a new shortlink');
        }

        return $this->renderTemplate('shortlink-manager/shortlinks/edit', [
            'shortLink' => $shortLink,
            'title' => $title,
            'linkId' => $shortLinkId,
            'enabledSites' => ShortLinkManager::getInstance()->getEnabledSites(),
            'folderOptions' => ShortLinkManager::$plugin->taxonomy->getFolderOptions(),
            'tagString' => implode(', ', $shortLink->tagNames ?? []),
            'allTagNames' => ShortLinkManager::$plugin->taxonomy->getAllTagNames(),
            'commerceElementTypesAvailable' => $this->commerceElementTypesAvailable(),
            'commerceProductElementType' => 'craft\\commerce\\elements\\Product',
            'commerceVariantElementType' => 'craft\\commerce\\elements\\Variant',
        ]);
    }

    /**
     * Save a link
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $shortLinkId = $this->request->getBodyParam('linkId');
        $siteId = (int)($this->request->getBodyParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id);

        // Validate site exists
        $site = Craft::$app->getSites()->getSiteById($siteId);
        if (!$site) {
            throw new \yii\web\BadRequestHttpException('Invalid site ID.');
        }

        // Validate site is enabled for this plugin
        $settings = ShortLinkManager::$plugin->getSettings();
        if (!$settings->isSiteEnabled($siteId)) {
            throw new \yii\web\ForbiddenHttpException(Craft::t('shortlink-manager', '{pluginName} is not enabled for this site.', ['pluginName' => ShortLinkManager::$plugin->getSettings()->getFullName()]));
        }

        // Enforce site edit permission (multi-site only)
        if (Craft::$app->getIsMultiSite()) {
            $this->requirePermission('editSite:' . $site->uid);
        }

        if ($shortLinkId) {
            $this->requirePermission('shortLinkManager:editLinks');
            $shortLink = ShortLink::find()
                ->id($shortLinkId)
                ->siteId($siteId)
                ->status(null)
                ->one();

            if (!$shortLink) {
                throw new \yii\web\NotFoundHttpException(Craft::t('shortlink-manager', 'ShortLink not found'));
            }
        } else {
            $this->requirePermission('shortLinkManager:createLinks');
            $shortLink = new ShortLink();
            $shortLink->siteId = $siteId;
        }

        // Populate from request
        $shortLink->linkType = $this->request->getBodyParam('linkType', 'code');
        $shortLink->code = $this->request->getBodyParam('code');

        // Note: slug will be auto-generated from code in beforeValidate()
        $shortLink->httpCode = $this->request->getBodyParam('httpCode')
            ?: (ShortLinkManager::$plugin->getSettings()->defaultHttpCode ?? 302);

        // Use setEnabledForSite for per-site enabling (elements_sites.enabled)
        $enabled = (bool) $this->request->getBodyParam('enabled', true);
        $shortLink->setEnabledForSite($enabled);

        // Handle destination based on shortLinkType
        if ($shortLink->shortLinkType !== 'auto') {
            // Manual shortlinks: process destination type selection
            $destinationType = $this->request->getBodyParam('destinationType', 'url');

            if ($destinationType === 'url') {
                // Custom URL: clear element link
                $shortLink->elementId = null;
                $shortLink->elementType = null;
                $shortLink->destinationUrl = $this->request->getBodyParam('destinationUrl');
            } else {
                // Element-based destination
                $elementTypeMap = [
                    'entry' => \craft\elements\Entry::class,
                    'category' => \craft\elements\Category::class,
                    'asset' => \craft\elements\Asset::class,
                ];
                if ($this->commerceElementTypesAvailable()) {
                    $elementTypeMap['product'] = 'craft\\commerce\\elements\\Product';
                    $elementTypeMap['variant'] = 'craft\\commerce\\elements\\Variant';
                }

                $elementFieldName = 'destinationElement' . ucfirst($destinationType);
                $elementIds = $this->request->getBodyParam($elementFieldName);
                $elementId = is_array($elementIds) ? ($elementIds[0] ?? null) : $elementIds;

                if ($elementId && isset($elementTypeMap[$destinationType])) {
                    $shortLink->elementId = (int)$elementId;
                    $shortLink->elementType = $elementTypeMap[$destinationType];

                    // Resolve destination URL from element for the CURRENT site
                    // This ensures each site gets the correct URL for its version of the element
                    $element = Craft::$app->elements->getElementById(
                        (int)$elementId,
                        $shortLink->elementType,
                        $shortLink->siteId
                    );
                    if ($element) {
                        $shortLink->destinationUrl = $element->getUrl() ?? '';
                    } else {
                        // Element doesn't exist on this site - try to get URL from any site as fallback
                        $element = Craft::$app->elements->getElementById((int)$elementId, $shortLink->elementType, '*');
                        $shortLink->destinationUrl = $element ? ($element->getUrl() ?? '') : '';
                    }
                }
            }
        } else {
            // Auto (field-managed) shortlinks: just preserve destinationUrl from form
            $shortLink->destinationUrl = $this->request->getBodyParam('destinationUrl');
        }

        // Handle author
        $authorId = $this->request->getBodyParam('authorId');
        if (is_array($authorId)) {
            $shortLink->authorId = !empty($authorId[0]) ? (int)$authorId[0] : null;
        } else {
            $shortLink->authorId = $authorId ? (int)$authorId : null;
        }

        // Handle post date
        $postDate = $this->request->getBodyParam('postDate');
        if ($postDate) {
            $dateTime = \craft\helpers\DateTimeHelper::toDateTime($postDate);
            $shortLink->postDate = $dateTime instanceof \DateTime ? $dateTime : null;
        }

        // Handle expiry date field
        $expiryDate = $this->request->getBodyParam('expiryDate');
        if ($expiryDate) {
            $dateTime = \craft\helpers\DateTimeHelper::toDateTime($expiryDate);
            $shortLink->dateExpired = $dateTime instanceof \DateTime ? $dateTime : null;
        } else {
            $shortLink->dateExpired = null;
        }

        $shortLink->expiredRedirectUrl = $this->request->getBodyParam('expiredRedirectUrl');
        $shortLink->expiredMessage = $this->request->getBodyParam('expiredMessage');
        $trackAnalytics = $this->request->getBodyParam('trackAnalytics');
        if ($trackAnalytics !== null) {
            $shortLink->trackAnalytics = (bool)$trackAnalytics;
        }

        // Handle passQueryParams - only set if explicitly provided (preserves existing value for API callers)
        $passQueryParams = $this->request->getBodyParam('passQueryParams');
        if ($passQueryParams !== null) {
            $shortLink->passQueryParams = (bool) $passQueryParams;
        }

        // Handle directRedirect - only set if explicitly provided (preserves existing value for API callers)
        $directRedirect = $this->request->getBodyParam('directRedirect');
        if ($directRedirect !== null) {
            $shortLink->directRedirect = (bool) $directRedirect;
        }

        // QR Code settings
        $shortLink->qrCodeEnabled = (bool) $this->request->getBodyParam('qrCodeEnabled', true);
        $shortLink->qrCodeSize = (int) $this->request->getBodyParam('qrCodeSize', 256);

        // Handle color fields - add # if missing, or set to null if empty
        $qrCodeColor = $this->request->getBodyParam('qrCodeColor');
        $shortLink->qrCodeColor = $qrCodeColor ? (str_starts_with($qrCodeColor, '#') ? $qrCodeColor : '#' . $qrCodeColor) : null;

        $qrCodeBgColor = $this->request->getBodyParam('qrCodeBgColor');
        $shortLink->qrCodeBgColor = $qrCodeBgColor ? (str_starts_with($qrCodeBgColor, '#') ? $qrCodeBgColor : '#' . $qrCodeBgColor) : null;

        $qrCodeEyeColor = $this->request->getBodyParam('qrCodeEyeColor');
        $shortLink->qrCodeEyeColor = $qrCodeEyeColor ? (str_starts_with($qrCodeEyeColor, '#') ? $qrCodeEyeColor : '#' . $qrCodeEyeColor) : null;

        $shortLink->qrCodeFormat = $this->request->getBodyParam('qrCodeFormat') ?: null;

        // Validate qrLogoId server-side against the configured volume + the user's
        // viewAssets permission. The field's source restriction is client-side only,
        // so a crafted POST could otherwise embed any asset as the QR logo.
        $shortLink->qrLogoId = AssetVolumeHelper::validateAssetId(
            $this->request->getBodyParam('qrLogoId'),
            ShortLinkManager::$plugin->getSettings()->qrLogoVolumeUid,
        );

        // Folder/tags (plugin-internal taxonomy)
        $folderIdParam = (string)$this->request->getBodyParam('folderId', '');
        $newFolderName = trim((string)$this->request->getBodyParam('newFolderName', ''));
        if ($folderIdParam === '__new__') {
            if ($newFolderName === '') {
                $shortLink->folderId = null;
            } else {
                $folderId = ShortLinkManager::$plugin->taxonomy->getOrCreateFolderByName($newFolderName);
                $shortLink->folderId = $folderId > 0 ? $folderId : null;
            }
        } elseif ($newFolderName !== '') {
            $folderId = ShortLinkManager::$plugin->taxonomy->getOrCreateFolderByName($newFolderName);
            $shortLink->folderId = $folderId > 0 ? $folderId : null;
        } else {
            $folderId = $this->request->getBodyParam('folderId');
            $shortLink->folderId = $folderId !== null && $folderId !== '' ? (int)$folderId : null;
        }

        $tagInput = $this->request->getBodyParam('tags', []);
        $tagValues = [];
        if (is_array($tagInput)) {
            foreach (array_slice(array_values($tagInput), 0, 100) as $value) {
                if (is_scalar($value)) {
                    $tagValues[] = (string)$value;
                }
            }
        } elseif (is_string($tagInput) && $tagInput !== '') {
            $tagValues = array_slice(explode(',', $tagInput), 0, 100);
        }
        $shortLink->setTagNames($tagValues);

        // Save the link using service (handles slug change redirects)
        if (!ShortLinkManager::$plugin->shortLinks->saveShortLink($shortLink)) {
            $this->setFailFlash(Craft::t('shortlink-manager', 'Could not save shortlink.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'shortLink' => $shortLink,
            ]);

            return null;
        }

        $this->setSuccessFlash(Craft::t('shortlink-manager', 'ShortLink saved.'));

        // Redirect to edit page or posted URL
        return $this->redirectToPostedUrl($shortLink);
    }

    private function commerceElementTypesAvailable(): bool
    {
        return PluginHelper::isPluginInstalled('commerce')
            && class_exists('craft\\commerce\\elements\\Product')
            && class_exists('craft\\commerce\\elements\\Variant');
    }

    /**
     * Delete a link
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:deleteLinks');

        $shortLinkId = $this->request->getRequiredBodyParam('id');

        $shortLink = ShortLink::find()
            ->id($shortLinkId)
            ->siteId('*')
            ->status(null)
            ->one();

        if (!$shortLink) {
            throw new \yii\web\NotFoundHttpException(Craft::t('shortlink-manager', 'ShortLink not found'));
        }

        // Enforce site edit permission (multi-site only)
        if (Craft::$app->getIsMultiSite()) {
            $site = Craft::$app->getSites()->getSiteById($shortLink->siteId);
            if ($site) {
                $this->requirePermission('editSite:' . $site->uid);
            }
        }

        if (Craft::$app->elements->deleteElement($shortLink)) {
            $this->setSuccessFlash(Craft::t('shortlink-manager', 'ShortLink deleted.'));
        } else {
            $this->setFailFlash(Craft::t('shortlink-manager', 'Could not delete shortlink.'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Generate a unique code/slug
     *
     * @return Response
     */
    public function actionGenerateCode(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission('shortLinkManager:createLinks');

        $settings = ShortLinkManager::$plugin->getSettings();
        $code = ShortLinkManager::$plugin->shortLinks->generateUniqueSlug($settings->codeLength ?? 8);

        return $this->asJson([
            'success' => true,
            'code' => $code,
        ]);
    }

    public function actionBulkSetFolder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('shortLinkManager:editLinks');

        $ids = $this->normalizeBulkIds($this->request->getBodyParam('ids', []));
        $folderName = trim((string)$this->request->getBodyParam('folderName', ''));

        if (empty($ids)) {
            return $this->asJsonFailure(Craft::t('shortlink-manager', 'No shortlinks selected.'));
        }

        if ($folderName === '') {
            return $this->asJsonFailure(Craft::t('shortlink-manager', 'Folder name cannot be empty.'));
        }

        $folderId = ShortLinkManager::$plugin->taxonomy->getOrCreateFolderByName($folderName);
        if ($folderId <= 0) {
            return $this->asJsonFailure(Craft::t('shortlink-manager', 'Could not create folder.'));
        }

        $affected = Db::update('{{%shortlinkmanager}}', ['folderId' => $folderId], ['id' => $ids]);
        $this->invalidateBulkElementCaches($ids);

        return $this->asJson([
            'success' => true,
            'message' => Craft::t('shortlink-manager', 'Folder updated for {count} shortlinks.', ['count' => $affected]),
        ]);
    }

    public function actionBulkClearFolder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('shortLinkManager:editLinks');

        $ids = $this->normalizeBulkIds($this->request->getBodyParam('ids', []));
        if (empty($ids)) {
            return $this->asJsonFailure(Craft::t('shortlink-manager', 'No shortlinks selected.'));
        }

        $affected = Db::update('{{%shortlinkmanager}}', ['folderId' => null], ['id' => $ids]);
        $this->invalidateBulkElementCaches($ids);

        return $this->asJson([
            'success' => true,
            'message' => Craft::t('shortlink-manager', 'Folder cleared for {count} shortlinks.', ['count' => $affected]),
        ]);
    }

    public function actionBulkAddTags(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('shortLinkManager:editLinks');

        $ids = $this->normalizeBulkIds($this->request->getBodyParam('ids', []));
        $inputTags = $this->parseTagList($this->request->getBodyParam('tags', ''));

        if (empty($ids)) {
            return $this->asJsonFailure(Craft::t('shortlink-manager', 'No shortlinks selected.'));
        }

        if (empty($inputTags)) {
            return $this->asJsonFailure(Craft::t('shortlink-manager', 'Tags cannot be empty.'));
        }

        $existingByLink = ShortLinkManager::$plugin->taxonomy->getTagNamesForShortLinks($ids);
        foreach ($ids as $id) {
            $existing = $existingByLink[$id] ?? [];
            $merged = array_values(array_unique(array_merge($existing, $inputTags)));
            ShortLinkManager::$plugin->taxonomy->syncShortLinkTagsByNames($id, $merged);
        }
        $this->invalidateBulkElementCaches($ids);

        return $this->asJson([
            'success' => true,
            'message' => Craft::t('shortlink-manager', 'Tags added for {count} shortlinks.', ['count' => count($ids)]),
        ]);
    }

    public function actionBulkRemoveTags(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('shortLinkManager:editLinks');

        $ids = $this->normalizeBulkIds($this->request->getBodyParam('ids', []));
        $removeTags = $this->parseTagList($this->request->getBodyParam('tags', ''));

        if (empty($ids)) {
            return $this->asJsonFailure(Craft::t('shortlink-manager', 'No shortlinks selected.'));
        }

        if (empty($removeTags)) {
            return $this->asJsonFailure(Craft::t('shortlink-manager', 'Tags cannot be empty.'));
        }

        $removeLookup = array_fill_keys(array_map(static fn(string $tag): string => mb_strtolower($tag), $removeTags), true);

        $existingByLink = ShortLinkManager::$plugin->taxonomy->getTagNamesForShortLinks($ids);
        foreach ($ids as $id) {
            $existing = $existingByLink[$id] ?? [];
            $filtered = array_values(array_filter($existing, static function(string $tag) use ($removeLookup): bool {
                return !isset($removeLookup[mb_strtolower($tag)]);
            }));
            ShortLinkManager::$plugin->taxonomy->syncShortLinkTagsByNames($id, $filtered);
        }
        $this->invalidateBulkElementCaches($ids);

        return $this->asJson([
            'success' => true,
            'message' => Craft::t('shortlink-manager', 'Tags removed for {count} shortlinks.', ['count' => count($ids)]),
        ]);
    }

    public function actionBulkClearTags(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('shortLinkManager:editLinks');

        $ids = $this->normalizeBulkIds($this->request->getBodyParam('ids', []));
        if (empty($ids)) {
            return $this->asJsonFailure(Craft::t('shortlink-manager', 'No shortlinks selected.'));
        }

        foreach ($ids as $id) {
            ShortLinkManager::$plugin->taxonomy->syncShortLinkTagsByNames($id, []);
        }
        $this->invalidateBulkElementCaches($ids);

        return $this->asJson([
            'success' => true,
            'message' => Craft::t('shortlink-manager', 'Tags cleared for {count} shortlinks.', ['count' => count($ids)]),
        ]);
    }

    /**
     * @param mixed $idsParam
     * @return array<int, int>
     */
    private function normalizeBulkIds(mixed $idsParam): array
    {
        $ids = [];
        if (is_array($idsParam)) {
            foreach ($idsParam as $id) {
                $value = (int)$id;
                if ($value > 0) {
                    $ids[] = $value;
                }
            }
        } elseif (is_string($idsParam) && $idsParam !== '') {
            foreach (explode(',', $idsParam) as $id) {
                $value = (int)trim($id);
                if ($value > 0) {
                    $ids[] = $value;
                }
            }
        }

        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            return [];
        }

        $query = (new Query())
            ->select(['id'])
            ->from('{{%shortlinkmanager}}')
            ->where(['id' => $ids]);

        return array_map('intval', $query->column());
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function parseTagList(mixed $value): array
    {
        if (is_array($value)) {
            $values = $value;
        } elseif (is_string($value)) {
            $values = preg_split('/\s*,\s*/', trim($value)) ?: [];
        } else {
            $values = [];
        }

        $values = array_map(static fn(mixed $item): string => trim((string)$item), $values);

        return array_values(array_unique(array_filter($values, static fn(string $tag): bool => $tag !== '')));
    }

    private function asJsonFailure(string $message): Response
    {
        Craft::$app->getResponse()->setStatusCode(400);
        return $this->asJson([
            'success' => false,
            'error' => $message,
        ]);
    }

    /**
     * @param array<int, int> $ids
     */
    private function invalidateBulkElementCaches(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        // Touch element timestamps so CP index views refresh immediately.
        Db::update(
            Table::ELEMENTS,
            ['dateUpdated' => Db::prepareDateForDb(new \DateTime())],
            ['id' => $ids],
            [],
            false
        );

        Craft::$app->getElements()->invalidateCachesForElementType(ShortLink::class);
    }
}
