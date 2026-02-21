<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\base\helpers\CpNavHelper;
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
     * @since 5.0.0
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
     * @since 5.0.0
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
                    throw new \yii\web\ForbiddenHttpException('ShortLink Manager is not enabled for this site.');
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
                    throw new \yii\web\NotFoundHttpException('ShortLink not found');
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
                    throw new \yii\web\ForbiddenHttpException('ShortLink Manager is not enabled for this site.');
                }

                // Enforce site edit permission (multi-site only)
                if (Craft::$app->getIsMultiSite()) {
                    $this->requirePermission('editSite:' . $site->uid);
                }

                $shortLink = new ShortLink();
                $shortLink->siteId = $site->id;
                $shortLink->enabled = true;
                $shortLink->httpCode = ShortLinkManager::$plugin->getSettings()->defaultHttpCode ?? 301;
                $shortLink->linkType = 'code'; // Default to auto-generated
            }

            $title = Craft::t('shortlink-manager', 'Create a new shortlink');
        }

        return $this->renderTemplate('shortlink-manager/shortlinks/edit', [
            'shortLink' => $shortLink,
            'title' => $title,
            'linkId' => $shortLinkId,
            'enabledSites' => ShortLinkManager::getInstance()->getEnabledSites(),
        ]);
    }

    /**
     * Save a link
     *
     * @return Response|null
     * @since 5.0.0
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
            throw new \yii\web\ForbiddenHttpException('ShortLink Manager is not enabled for this site.');
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
                throw new \yii\web\NotFoundHttpException('ShortLink not found');
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
        $shortLink->httpCode = $this->request->getBodyParam('httpCode') ?: 301;

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
        $shortLink->trackAnalytics = (bool) $this->request->getBodyParam('trackAnalytics', true);

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

        // Handle qrLogoId (asset field returns array)
        $qrLogoId = $this->request->getBodyParam('qrLogoId');
        if (is_array($qrLogoId)) {
            $shortLink->qrLogoId = !empty($qrLogoId[0]) ? (int)$qrLogoId[0] : null;
        } else {
            $shortLink->qrLogoId = $qrLogoId ? (int)$qrLogoId : null;
        }

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

    /**
     * Delete a link
     *
     * @return Response
     * @since 5.0.0
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
            throw new \yii\web\NotFoundHttpException('ShortLink not found');
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
     * @since 5.0.0
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
}
