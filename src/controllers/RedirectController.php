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
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use yii\web\Response;

/**
 * Redirect Controller
 *
 * Handles front-end shortlink redirects
 */
class RedirectController extends Controller
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
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = ['index'];

    /**
     * Handle shortlink redirect
     *
     * @param string|null $code
     * @return Response
     */
    public function actionIndex(?string $code = null): Response
    {
        $this->logDebug('Shortlink redirect requested', ['code' => $code]);

        if (!$code) {
            $this->logWarning('Shortlink code missing');
            return $this->redirectToNotFound();
        }

        // Get the shortlink
        $link = ShortLinkManager::$plugin->shortLinks->getByCode($code);

        if (!$link) {
            $this->logWarning('Shortlink not found', ['code' => $code]);
            return $this->redirectToNotFound();
        }

        $this->logDebug('Shortlink found', [
            'slug' => $link->slug,
            'destinationUrl' => $link->destinationUrl,
            'elementId' => $link->elementId,
        ]);

        // Check if enabled (using element status)
        if ($link->getStatus() === \lindemannrock\shortlinkmanager\elements\ShortLink::STATUS_DISABLED) {
            $this->logInfo('Shortlink disabled', ['code' => $code]);
            return $this->redirectToNotFound();
        }

        // Check expiration
        if ($link->isExpired()) {
            $this->logInfo('Shortlink expired', ['code' => $code]);
            return $this->handleExpiredLink($link);
        }

        // Get destination URL
        $destinationUrl = $link->destinationUrl;

        // If destination is empty, try to get from linked element
        if (empty($destinationUrl) && $link->elementId) {
            $this->logDebug('Fetching URL from linked element', [
                'elementId' => $link->elementId,
                'elementType' => $link->elementType,
            ]);

            $element = $link->getLinkedElement();
            if ($element) {
                $destinationUrl = $element->getUrl();
                $this->logDebug('Element URL retrieved', ['url' => $destinationUrl]);
            } else {
                $this->logError('Linked element not found', ['elementId' => $link->elementId]);
            }
        }

        // If still empty, redirect to not found
        if (empty($destinationUrl)) {
            $this->logError('No destination URL available', [
                'slug' => $link->slug,
                'elementId' => $link->elementId,
            ]);
            return $this->redirectToNotFound();
        }

        $this->logInfo('Redirecting shortlink', [
            'slug' => $link->slug,
            'destination' => $destinationUrl,
            'httpCode' => $link->httpCode,
        ]);

        // Get source parameter for QR tracking (like Smart Links does)
        $source = Craft::$app->getRequest()->getParam('src', 'direct');

        // Get device info for analytics and SEOmatic tracking
        $deviceInfo = ShortLinkManager::$plugin->deviceDetection->detectDevice();

        // Track analytics if enabled globally AND for this specific link
        if ($link->trackAnalytics && ShortLinkManager::$plugin->getSettings()->enableAnalytics) {
            ShortLinkManager::$plugin->analytics->trackClick(
                $link,
                Craft::$app->getRequest(),
                $source
            );
        }

        // Track SEOmatic event if integration is enabled
        $seomatic = ShortLinkManager::$plugin->integration->getIntegration('seomatic');
        if ($seomatic && $seomatic->isAvailable() && $seomatic->isEnabled()) {
            // Determine event type based on source
            $eventType = ($source === 'qr') ? 'qr_scan' : 'redirect';

            $this->logInfo("SEOmatic client-side tracking: {$eventType} event for '{$link->code}'", [
                'event_type' => $eventType,
                'code' => $link->code,
                'source' => $source,
                'destination' => $destinationUrl,
            ]);
        }

        // Increment hit counter
        ShortLinkManager::$plugin->shortLinks->incrementHits($link);

        // Render redirect template instead of direct redirect
        // This allows for SEOmatic client-side tracking before redirect
        $settings = ShortLinkManager::$plugin->getSettings();
        $template = $settings->redirectTemplate ?: 'shortlink-manager/redirect';

        // Determine event type for template
        $eventType = ($source === 'qr') ? 'qr_scan' : 'redirect';

        return $this->renderTemplate($template, [
            'link' => $link,
            'destinationUrl' => $destinationUrl,
            'source' => $source,
            'deviceInfo' => $deviceInfo,
            'eventType' => $eventType,
        ]);
    }

    /**
     * Handle expired link
     *
     * @param \lindemannrock\shortlinkmanager\elements\ShortLink $link
     * @return Response
     */
    private function handleExpiredLink($link): Response
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        // Redirect to custom expired URL if set
        if ($link->expiredRedirectUrl) {
            return $this->redirect($link->expiredRedirectUrl, 302);
        }

        // Show expired message
        $message = $settings->expiredMessage ?? 'This link has expired';

        // Get custom template path or use default
        $template = $settings->expiredTemplate ?: 'shortlink-manager/expired';

        // Render the expired template (user must create it in their site templates)
        return $this->renderTemplate($template, [
            'message' => $message,
            'link' => $link
        ]);
    }

    /**
     * Redirect to not found URL
     *
     * @return Response
     */
    private function redirectToNotFound(): Response
    {
        $url = Craft::$app->getRequest()->getUrl();

        // Check Redirect Manager for matching redirect (if installed)
        $redirect = $this->handleRedirect404($url, 'shortlink-manager', [
            'type' => 'shortlink-not-found'
        ]);

        if ($redirect) {
            $this->logInfo('Shortlink 404 handled by Redirect Manager', [
                'url' => $url,
                'destination' => $redirect['destinationUrl']
            ]);

            return $this->redirect($redirect['destinationUrl'], $redirect['statusCode']);
        }

        // Fallback to configured URL
        $settings = ShortLinkManager::$plugin->getSettings();
        $notFoundUrl = $settings->notFoundRedirectUrl ?? '/';

        return $this->redirect($notFoundUrl, 302);
    }
}
