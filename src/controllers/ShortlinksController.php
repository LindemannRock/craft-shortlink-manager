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
        $this->setLoggingHandle('shortlink-manager');
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

        // If user doesn't have viewLinks permission, redirect to first accessible section
        if (!$user->checkPermission('shortLinkManager:viewLinks')) {
            if ($user->checkPermission('shortLinkManager:viewAnalytics') && $settings->enableAnalytics) {
                return $this->redirect('shortlink-manager/analytics');
            }
            if ($user->checkPermission('shortLinkManager:viewSystemLogs')) {
                return $this->redirect('shortlink-manager/logs');
            }
            if ($user->checkPermission('shortLinkManager:manageSettings')) {
                return $this->redirect('shortlink-manager/settings');
            }
            // No access at all
            $this->requirePermission('shortLinkManager:viewLinks');
        }

        // Get current site from request or Craft's current site
        $siteHandle = $this->request->getParam('site');
        $currentSite = $siteHandle
            ? Craft::$app->getSites()->getSiteByHandle($siteHandle)
            : Craft::$app->getSites()->getCurrentSite();

        // If current site is not enabled, redirect to first enabled site
        if (!$settings->isSiteEnabled($currentSite->id)) {
            $enabledSiteIds = $settings->getEnabledSiteIds();
            if (!empty($enabledSiteIds)) {
                $firstEnabledSite = Craft::$app->getSites()->getSiteById($enabledSiteIds[0]);
                if ($firstEnabledSite) {
                    return $this->redirect('shortlink-manager?site=' . $firstEnabledSite->handle);
                }
            }
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
                $site = $siteHandle ? Craft::$app->getSites()->getSiteByHandle($siteHandle) : Craft::$app->getSites()->getCurrentSite();

                // Check if ShortLink Manager is enabled for this site
                $settings = ShortLinkManager::getInstance()->getSettings();
                if (!$settings->isSiteEnabled($site->id)) {
                    throw new \yii\web\ForbiddenHttpException('ShortLink Manager is not enabled for this site.');
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
                $site = $siteHandle ? Craft::$app->getSites()->getSiteByHandle($siteHandle) : Craft::$app->getSites()->getCurrentSite();

                // Check if ShortLink Manager is enabled for this site
                $settings = ShortLinkManager::getInstance()->getSettings();
                if (!$settings->isSiteEnabled($site->id)) {
                    throw new \yii\web\ForbiddenHttpException('ShortLink Manager is not enabled for this site.');
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
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $shortLinkId = $this->request->getBodyParam('linkId');

        if ($shortLinkId) {
            $this->requirePermission('shortLinkManager:editLinks');
            $shortLink = ShortLink::find()
                ->id($shortLinkId)
                ->siteId('*')
                ->status(null)
                ->one();

            if (!$shortLink) {
                throw new \yii\web\NotFoundHttpException('ShortLink not found');
            }
        } else {
            $this->requirePermission('shortLinkManager:createLinks');
            $shortLink = new ShortLink();
        }

        // Populate from request
        $shortLink->linkType = $this->request->getBodyParam('linkType', 'code');
        $shortLink->code = $this->request->getBodyParam('code');

        // Note: slug will be auto-generated from code in beforeValidate()

        $shortLink->siteId = $this->request->getBodyParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id;
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
            $errors = $shortLink->getErrors();
            $errorMessage = Craft::t('shortlink-manager', 'Could not save shortlink.');
            if (!empty($errors)) {
                $errorMessage .= ' Errors: ' . print_r($errors, true);
            }
            $this->setFailFlash($errorMessage);

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
}
