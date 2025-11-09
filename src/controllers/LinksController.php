<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use yii\web\Response;

/**
 * Links Controller
 */
class LinksController extends Controller
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
        $this->requirePermission('shortLinkManager:viewLinks');

        return $this->renderTemplate('shortlink-manager/links/index');
    }

    /**
     * Edit a link
     *
     * @param int|null $linkId
     * @param ShortLink|null $link
     * @return Response
     */
    public function actionEdit(?int $linkId = null, ?ShortLink $link = null): Response
    {
        if ($linkId) {
            $this->requirePermission('shortLinkManager:editLinks');

            if (!$link) {
                // Get site from request or use current site
                $siteHandle = $this->request->getParam('site');
                $site = $siteHandle ? Craft::$app->getSites()->getSiteByHandle($siteHandle) : Craft::$app->getSites()->getCurrentSite();

                // Check if ShortLink Manager is enabled for this site
                $settings = ShortLinkManager::getInstance()->getSettings();
                if (!$settings->isSiteEnabled($site->id)) {
                    throw new \yii\web\ForbiddenHttpException('ShortLink Manager is not enabled for this site.');
                }

                $link = ShortLink::find()
                    ->id($linkId)
                    ->siteId($site->id)
                    ->status(null)
                    ->one();

                if (!$link) {
                    throw new \yii\web\NotFoundHttpException('ShortLink not found');
                }
            }

            $title = $link->code ?? $link->slug;
        } else {
            $this->requirePermission('shortLinkManager:createLinks');

            if (!$link) {
                // Get site from request or use current site
                $siteHandle = $this->request->getParam('site');
                $site = $siteHandle ? Craft::$app->getSites()->getSiteByHandle($siteHandle) : Craft::$app->getSites()->getCurrentSite();

                // Check if ShortLink Manager is enabled for this site
                $settings = ShortLinkManager::getInstance()->getSettings();
                if (!$settings->isSiteEnabled($site->id)) {
                    throw new \yii\web\ForbiddenHttpException('ShortLink Manager is not enabled for this site.');
                }

                $link = new ShortLink();
                $link->siteId = $site->id;
                $link->enabled = true;
                $link->httpCode = ShortLinkManager::$plugin->getSettings()->defaultHttpCode ?? 301;
                $link->linkType = 'code'; // Default to auto-generated
            }

            $title = Craft::t('shortlink-manager', 'Create a new shortlink');
        }

        return $this->renderTemplate('shortlink-manager/links/edit', [
            'link' => $link,
            'title' => $title,
            'linkId' => $linkId,
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

        $linkId = $this->request->getBodyParam('linkId');

        if ($linkId) {
            $this->requirePermission('shortLinkManager:editLinks');
            $link = ShortLink::find()
                ->id($linkId)
                ->siteId('*')
                ->status(null)
                ->one();

            if (!$link) {
                throw new \yii\web\NotFoundHttpException('ShortLink not found');
            }
        } else {
            $this->requirePermission('shortLinkManager:createLinks');
            $link = new ShortLink();
        }

        // Populate from request
        $link->linkType = $this->request->getBodyParam('linkType', 'code');
        $link->code = $this->request->getBodyParam('code');

        // Note: slug will be auto-generated from code in beforeValidate()

        $link->destinationUrl = $this->request->getBodyParam('destinationUrl');
        $link->siteId = $this->request->getBodyParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id;
        $link->httpCode = $this->request->getBodyParam('httpCode') ?: 301;
        $link->enabled = (bool) $this->request->getBodyParam('enabled', true);

        // Handle element relationship
        $link->elementId = $this->request->getBodyParam('elementId');
        $link->elementType = $this->request->getBodyParam('elementType');

        // Handle author
        $authorId = $this->request->getBodyParam('authorId');
        if (is_array($authorId)) {
            $link->authorId = !empty($authorId[0]) ? (int)$authorId[0] : null;
        } else {
            $link->authorId = $authorId ? (int)$authorId : null;
        }

        // Handle post date
        $postDate = $this->request->getBodyParam('postDate');
        if ($postDate) {
            $dateTime = \craft\helpers\DateTimeHelper::toDateTime($postDate);
            $link->postDate = $dateTime instanceof \DateTime ? $dateTime : null;
        }

        // Handle expiry date field
        $expiryDate = $this->request->getBodyParam('expiryDate');
        if ($expiryDate) {
            $dateTime = \craft\helpers\DateTimeHelper::toDateTime($expiryDate);
            $link->dateExpired = $dateTime instanceof \DateTime ? $dateTime : null;
        } else {
            $link->dateExpired = null;
        }

        $link->expiredRedirectUrl = $this->request->getBodyParam('expiredRedirectUrl');
        $link->trackAnalytics = (bool) $this->request->getBodyParam('trackAnalytics', true);

        // QR Code settings
        $link->qrCodeEnabled = (bool) $this->request->getBodyParam('qrCodeEnabled', true);
        $link->qrCodeSize = (int) $this->request->getBodyParam('qrCodeSize', 256);

        // Handle color fields - add # if missing, or set to null if empty
        $qrCodeColor = $this->request->getBodyParam('qrCodeColor');
        $link->qrCodeColor = $qrCodeColor ? (str_starts_with($qrCodeColor, '#') ? $qrCodeColor : '#' . $qrCodeColor) : null;

        $qrCodeBgColor = $this->request->getBodyParam('qrCodeBgColor');
        $link->qrCodeBgColor = $qrCodeBgColor ? (str_starts_with($qrCodeBgColor, '#') ? $qrCodeBgColor : '#' . $qrCodeBgColor) : null;

        $qrCodeEyeColor = $this->request->getBodyParam('qrCodeEyeColor');
        $link->qrCodeEyeColor = $qrCodeEyeColor ? (str_starts_with($qrCodeEyeColor, '#') ? $qrCodeEyeColor : '#' . $qrCodeEyeColor) : null;

        $link->qrCodeFormat = $this->request->getBodyParam('qrCodeFormat') ?: null;

        // Handle qrLogoId (asset field returns array)
        $qrLogoId = $this->request->getBodyParam('qrLogoId');
        if (is_array($qrLogoId)) {
            $link->qrLogoId = !empty($qrLogoId[0]) ? (int)$qrLogoId[0] : null;
        } else {
            $link->qrLogoId = $qrLogoId ? (int)$qrLogoId : null;
        }

        // Save the link using service (handles slug change redirects)
        if (!ShortLinkManager::$plugin->shortLinks->saveShortLink($link)) {
            $errors = $link->getErrors();
            $errorMessage = Craft::t('shortlink-manager', 'Could not save shortlink.');
            if (!empty($errors)) {
                $errorMessage .= ' Errors: ' . print_r($errors, true);
            }
            $this->setFailFlash($errorMessage);

            Craft::$app->getUrlManager()->setRouteParams([
                'link' => $link,
            ]);

            return null;
        }

        $this->setSuccessFlash(Craft::t('shortlink-manager', 'ShortLink saved.'));

        // Redirect to edit page or posted URL
        return $this->redirectToPostedUrl($link);
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

        $linkId = $this->request->getRequiredBodyParam('id');

        $link = ShortLink::find()
            ->id($linkId)
            ->siteId('*')
            ->status(null)
            ->one();

        if (!$link) {
            throw new \yii\web\NotFoundHttpException('ShortLink not found');
        }

        if (Craft::$app->elements->deleteElement($link)) {
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
