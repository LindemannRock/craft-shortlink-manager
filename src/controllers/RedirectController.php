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
        $shortLink = ShortLinkManager::$plugin->shortLinks->getByCode($code);

        if (!$shortLink) {
            $this->logWarning('Shortlink not found', ['code' => $code]);
            return $this->redirectToNotFound();
        }

        $this->logDebug('Shortlink found', [
            'slug' => $shortLink->slug,
            'destinationUrl' => $shortLink->destinationUrl,
            'elementId' => $shortLink->elementId,
        ]);

        // Check if enabled (using element status)
        if ($shortLink->getStatus() === \lindemannrock\shortlinkmanager\elements\ShortLink::STATUS_DISABLED) {
            $this->logInfo('Shortlink disabled', ['code' => $code]);
            return $this->redirectToNotFound();
        }

        // Check expiration
        if ($shortLink->isExpired()) {
            $this->logInfo('Shortlink expired', ['code' => $code]);
            return $this->handleExpiredLink($shortLink);
        }

        // Get destination URL
        $destinationUrl = $shortLink->destinationUrl;

        // If destination is empty, try to get from linked element
        if (empty($destinationUrl) && $shortLink->elementId) {
            $this->logDebug('Fetching URL from linked element', [
                'elementId' => $shortLink->elementId,
                'elementType' => $shortLink->elementType,
            ]);

            $element = $shortLink->getLinkedElement();
            if ($element) {
                $destinationUrl = $element->getUrl();
                $this->logDebug('Element URL retrieved', ['url' => $destinationUrl]);
            } else {
                $this->logError('Linked element not found', ['elementId' => $shortLink->elementId]);
            }
        }

        // If still empty, redirect to not found
        if (empty($destinationUrl)) {
            $this->logError('No destination URL available', [
                'slug' => $shortLink->slug,
                'elementId' => $shortLink->elementId,
            ]);
            return $this->redirectToNotFound();
        }

        $this->logInfo('Redirecting shortlink', [
            'slug' => $shortLink->slug,
            'destination' => $destinationUrl,
            'httpCode' => $shortLink->httpCode,
        ]);

        // Get source parameter for QR tracking (like Smart Links does)
        $source = Craft::$app->getRequest()->getParam('src', 'direct');

        // Get device info for analytics and SEOmatic tracking
        $deviceInfo = ShortLinkManager::$plugin->deviceDetection->detectDevice();

        // Track analytics if enabled globally AND for this specific link
        if ($shortLink->trackAnalytics && ShortLinkManager::$plugin->getSettings()->enableAnalytics) {
            ShortLinkManager::$plugin->analytics->trackClick(
                $shortLink,
                Craft::$app->getRequest(),
                $source
            );
        }

        // Track SEOmatic event if integration is enabled
        $seomatic = ShortLinkManager::$plugin->integration->getIntegration('seomatic');
        if ($seomatic && $seomatic->isAvailable() && $seomatic->isEnabled()) {
            // Determine event type based on source
            $eventType = ($source === 'qr') ? 'qr_scan' : 'redirect';

            $this->logInfo("SEOmatic client-side tracking: {$eventType} event for '{$shortLink->code}'", [
                'event_type' => $eventType,
                'code' => $shortLink->code,
                'source' => $source,
                'destination' => $destinationUrl,
            ]);
        }

        // Increment hit counter
        ShortLinkManager::$plugin->shortLinks->incrementHits($shortLink);

        // Render redirect template instead of direct redirect
        // This allows for SEOmatic client-side tracking before redirect
        $settings = ShortLinkManager::$plugin->getSettings();
        $template = $settings->redirectTemplate ?: 'shortlink-manager/redirect';

        // Determine event type for template
        $eventType = ($source === 'qr') ? 'qr_scan' : 'redirect';

        return $this->renderTemplate($template, [
            'shortLink' => $shortLink,
            'destinationUrl' => $destinationUrl,
            'source' => $source,
            'deviceInfo' => $deviceInfo,
            'eventType' => $eventType,
        ]);
    }

    /**
     * Handle expired link
     *
     * @param \lindemannrock\shortlinkmanager\elements\ShortLink $shortLink
     * @return Response
     */
    private function handleExpiredLink($shortLink): Response
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        // Redirect to custom expired URL if set
        if ($shortLink->expiredRedirectUrl) {
            return $this->redirect($shortLink->expiredRedirectUrl, 302);
        }

        // Show expired message
        $message = $settings->expiredMessage ?? 'This link has expired';

        // Get custom template path or use default
        $template = $settings->expiredTemplate ?: 'shortlink-manager/expired';

        // Render the expired template (user must create it in their site templates)
        return $this->renderTemplate($template, [
            'message' => $message,
            'shortLink' => $shortLink
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

    /**
     * Handle 404 through Redirect Manager if available
     *
     * @param string $url The URL that wasn't found
     * @param string $source Source identifier (e.g., 'shortlink-manager')
     * @param array $context Additional context data
     * @return array|null Redirect data or null if no redirect found
     */
    private function handleRedirect404(string $url, string $source, array $context = []): ?array
    {
        // Use the integration to check availability and enabled status
        $integration = ShortLinkManager::$plugin->integration->getIntegration('redirect-manager');
        if (!$integration || !$integration->isAvailable() || !$integration->isEnabled()) {
            return null;
        }

        try {
            // Get Redirect Manager plugin instance
            $redirectManager = Craft::$app->plugins->getPlugin('redirect-manager');
            if (!$redirectManager) {
                return null;
            }

            // Add source to context
            $context['source'] = $source;

            // Call the service method to handle external 404
            return $redirectManager->redirects->handleExternal404($url, $context);
        } catch (\Throwable $e) {
            $this->logError('Failed to check Redirect Manager for 404', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
