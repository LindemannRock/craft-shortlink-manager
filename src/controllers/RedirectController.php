<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\controllers;

use Craft;
use craft\helpers\App;
use craft\models\Site;
use craft\web\Controller;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\helpers\UrlSafetyHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\Response;

/**
 * Redirect Controller
 *
 * Handles front-end shortlink redirects
 *
 * @since 5.0.0
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
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
    }

    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = ['index', 'go'];

    /**
     * Handle shortlink redirect
     *
     * @param string|null $code
     * @param string|null $siteHandle
     * @return Response
     */
    public function actionIndex(?string $code = null, ?string $siteHandle = null): Response
    {
        $this->logDebug('Shortlink redirect requested', ['code' => $code, 'siteHandle' => $siteHandle]);

        if (!$code) {
            $this->logWarning('Shortlink code missing');
            return $this->redirectToNotFound();
        }

        $site = $this->resolveSite($siteHandle);
        if (!$site) {
            $this->logWarning('Invalid site handle for shortlink request', ['code' => $code, 'siteHandle' => $siteHandle]);
            return $this->redirectToNotFound();
        }

        $settings = ShortLinkManager::$plugin->getSettings();
        $shortLink = $this->findShortLink($code, $site);

        if (!$shortLink) {
            $this->logWarning('Shortlink not found', ['code' => $code]);
            return $this->redirectToNotFound();
        }

        // Validate against the shortlink's actual site, not the request-resolved site.
        if (!$settings->isSiteEnabled($shortLink->siteId)) {
            $this->logInfo('ShortLink Manager disabled for shortlink site', [
                'siteId' => $shortLink->siteId,
                'code' => $code,
            ]);
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

        // Check if pending (future post date)
        if ($shortLink->getStatus() === \lindemannrock\shortlinkmanager\elements\ShortLink::STATUS_PENDING) {
            $this->logInfo('Shortlink pending', ['code' => $code]);
            return $this->redirectToNotFound();
        }

        // Check expiration
        if ($shortLink->isExpired()) {
            $this->logInfo('Shortlink expired', ['code' => $code]);
            return $this->handleExpiredLink($shortLink);
        }

        $destinationUrl = $this->resolveDestinationUrl($shortLink);

        // If still empty, redirect to not found
        if (empty($destinationUrl)) {
            $this->logError('No destination URL available', [
                'slug' => $shortLink->slug,
                'elementId' => $shortLink->elementId,
            ]);
            return $this->redirectToNotFound();
        }

        // Check if we should pass query params to destination
        $settings = ShortLinkManager::$plugin->getSettings();
        $shouldPassQueryParams = $shortLink->passQueryParams ?? $settings->passQueryParams;

        if ($shouldPassQueryParams) {
            $destinationUrl = $this->mergeQueryParams($destinationUrl);
        }

        $this->logInfo('Redirecting shortlink', [
            'slug' => $shortLink->slug,
            'destination' => $destinationUrl,
            'httpCode' => $shortLink->httpCode,
            'passQueryParams' => $shouldPassQueryParams,
        ]);

        // Get source parameter for QR tracking — allowlist to prevent log injection / metadata bloat
        $rawSource = Craft::$app->getRequest()->getParam('src', 'direct');
        $source = in_array($rawSource, ['qr', 'direct'], true) ? $rawSource : 'direct';

        // Get device info for analytics and SEOmatic tracking
        $deviceInfo = ShortLinkManager::$plugin->deviceDetection->detectDevice();
        $shouldTrack = $shortLink->trackAnalytics && ShortLinkManager::$plugin->getSettings()->enableAnalytics;

        // Check if direct redirect is enabled (per-link override or global setting)
        $shouldDirectRedirect = $shortLink->directRedirect ?? $settings->directRedirect;

        if ($shouldDirectRedirect) {
            return $this->executeRedirect($shortLink, $destinationUrl, $source);
        }

        $template = $settings->redirectTemplate ?: 'shortlink-manager/redirect';
        $eventType = ($source === 'qr') ? 'qr_scan' : 'redirect';
        $goSite = Craft::$app->getSites()->getSiteById($shortLink->siteId);
        $goParams = [
            'src' => $source,
        ];
        if ($this->shouldIncludeSiteParamForTrackedUrl($settings->shortlinkBaseUrl ?? null) && $goSite !== null) {
            $goParams['site'] = $goSite->handle;
        }
        $goUrl = $settings->buildPublicUrl(
            'shortlink-manager/redirect/go/' . $shortLink->slug,
            $shortLink->siteId,
            $goParams
        );

        ShortLinkManager::$plugin->integration->prepareSeomaticMetadata($shortLink);

        $response = $this->renderTemplate($template, [
            'shortLink' => $shortLink,
            'goUrl' => $goUrl,
            'source' => $source,
            'deviceInfo' => $deviceInfo,
            'eventType' => $eventType,
        ]);

        if ($shouldTrack) {
            $this->applyNoStoreHeaders($response);
        }

        return $response;
    }

    /**
     * Execute the analytics write + final redirect on an uncached action route.
     */
    public function actionGo(?string $code = null, ?string $siteHandle = null): Response
    {
        $this->logDebug('Shortlink go requested', ['code' => $code, 'siteHandle' => $siteHandle]);

        if (!$code) {
            $this->logWarning('Shortlink code missing for go action');
            return $this->redirectToNotFound();
        }

        $site = $this->resolveSite($siteHandle);
        if (!$site) {
            $this->logWarning('Invalid site handle for shortlink go request', ['code' => $code, 'siteHandle' => $siteHandle]);
            return $this->redirectToNotFound();
        }

        $settings = ShortLinkManager::$plugin->getSettings();
        $shortLink = $this->findShortLink($code, $site);

        if (!$shortLink) {
            $this->logWarning('Shortlink not found for go action', ['code' => $code]);
            return $this->redirectToNotFound();
        }

        if (!$settings->isSiteEnabled($shortLink->siteId)) {
            $this->logInfo('ShortLink Manager disabled for shortlink site', [
                'siteId' => $shortLink->siteId,
                'code' => $code,
            ]);
            return $this->redirectToNotFound();
        }

        if ($shortLink->getStatus() === ShortLink::STATUS_DISABLED) {
            $this->logInfo('Shortlink disabled', ['code' => $code]);
            return $this->redirectToNotFound();
        }

        if ($shortLink->getStatus() === ShortLink::STATUS_PENDING) {
            $this->logInfo('Shortlink pending', ['code' => $code]);
            return $this->redirectToNotFound();
        }

        if ($shortLink->isExpired()) {
            $this->logInfo('Shortlink expired', ['code' => $code]);
            return $this->handleExpiredLink($shortLink);
        }

        $destinationUrl = $this->resolveDestinationUrl($shortLink);

        if (empty($destinationUrl)) {
            $this->logError('No destination URL available for go action', [
                'slug' => $shortLink->slug,
                'elementId' => $shortLink->elementId,
            ]);
            return $this->redirectToNotFound();
        }

        $shouldPassQueryParams = $shortLink->passQueryParams ?? $settings->passQueryParams;
        if ($shouldPassQueryParams) {
            $destinationUrl = $this->mergeQueryParams($destinationUrl);
        }

        $rawSource = Craft::$app->getRequest()->getParam('src', 'direct');
        $source = in_array($rawSource, ['qr', 'direct'], true) ? $rawSource : 'direct';

        return $this->executeRedirect($shortLink, $destinationUrl, $source);
    }

    /**
     * Resolve request site from route handle (if provided), otherwise use current site.
     */
    private function resolveSite(?string $siteHandle): ?Site
    {
        if ($siteHandle) {
            return Craft::$app->getSites()->getSiteByHandle($siteHandle);
        }

        $siteParam = Craft::$app->getRequest()->getParam('site');
        if ($siteParam) {
            return Craft::$app->getSites()->getSiteByHandle((string)$siteParam);
        }

        return Craft::$app->getSites()->getCurrentSite();
    }

    private function shouldIncludeSiteParamForTrackedUrl(?string $baseUrl): bool
    {
        $baseUrl = trim((string) App::parseEnv($baseUrl ?? ''));

        return $baseUrl !== ''
            && !preg_match('/\{siteHandle\}|\{siteId\}|\{siteUid\}/', $baseUrl);
    }

    private function findShortLink(string $code, Site $site): ?ShortLink
    {
        $shortLink = ShortLinkManager::$plugin->shortLinks->getByCode($code, $site->id);

        if (!$shortLink) {
            $shortLink = ShortLinkManager::$plugin->shortLinks->getByCode($code, null);
        }

        return $shortLink;
    }

    private function resolveDestinationUrl(ShortLink $shortLink): ?string
    {
        $destinationUrl = $shortLink->destinationUrl;

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

        return $destinationUrl;
    }

    private function executeRedirect(ShortLink $shortLink, string $destinationUrl, string $source): Response
    {
        $shouldTrack = $shortLink->trackAnalytics && ShortLinkManager::$plugin->getSettings()->enableAnalytics;

        if ($shouldTrack) {
            ShortLinkManager::$plugin->analytics->trackClick(
                $shortLink,
                Craft::$app->getRequest(),
                $source
            );
        }

        $seomatic = ShortLinkManager::$plugin->integration->getIntegration('seomatic');
        if ($seomatic && $seomatic->isAvailable() && $seomatic->isEnabled()) {
            $eventType = ($source === 'qr') ? 'qr_scan' : 'redirect';

            $this->logInfo("SEOmatic client-side tracking: {$eventType} event for '{$shortLink->code}'", [
                'event_type' => $eventType,
                'code' => $shortLink->code,
                'source' => $source,
                'destination' => $destinationUrl,
            ]);
        }

        ShortLinkManager::$plugin->shortLinks->incrementHits($shortLink);

        $response = $this->redirect(
            $this->_sanitizeUrl($destinationUrl),
            $shortLink->httpCode ?? 302
        );

        if ($shouldTrack) {
            $this->applyNoStoreHeaders($response);
        }

        return $response;
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
            return $this->redirect($this->_sanitizeUrl($shortLink->expiredRedirectUrl), 302);
        }

        // Show expired message (use per-shortlink message if set, fall back to global default)
        $messageText = $shortLink->expiredMessage ?: ($settings->expiredMessage ?? 'This link has expired');
        $message = $this->_sanitizeMessage(Craft::t('shortlink-manager', $messageText));

        // Get custom template path or use default
        $template = $settings->expiredTemplate ?: 'shortlink-manager/expired';

        // Render the expired template (user must create it in their site templates)
        return $this->renderTemplate($template, [
            'message' => $message,
            'shortLink' => $shortLink,
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
            'type' => 'shortlink-not-found',
        ]);

        if ($redirect) {
            $this->logInfo('Shortlink 404 handled by Redirect Manager', [
                'url' => $url,
                'destination' => $redirect['destinationUrl'],
            ]);

            return $this->redirect($this->_sanitizeUrl($redirect['destinationUrl']), $redirect['statusCode']);
        }

        // Fallback to configured URL
        $settings = ShortLinkManager::$plugin->getSettings();
        $notFoundUrl = $this->_sanitizeUrl($settings->getResolvedNotFoundRedirectUrl());

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
            $redirectManager = PluginHelper::getPlugin('redirect-manager');
            if (!$redirectManager instanceof \lindemannrock\redirectmanager\RedirectManager) {
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

    /**
     * Merge query parameters from the request into the destination URL
     *
     * Excludes internal parameters (src, debug, p) and merges remaining
     * query params from the shortlink request into the destination URL.
     * Incoming params take precedence over existing destination params.
     *
     * @param string $destinationUrl The original destination URL
     * @return string The destination URL with merged query parameters
     */
    private function mergeQueryParams(string $destinationUrl): string
    {
        $request = Craft::$app->getRequest();

        // Get all query params from the request
        $incomingParams = $request->getQueryParams();

        // Remove internal params that shouldn't be passed through
        $excludeParams = ['src', 'debug', 'p'];
        foreach ($excludeParams as $param) {
            unset($incomingParams[$param]);
        }

        // If no params to merge, return original URL
        if (empty($incomingParams)) {
            return $destinationUrl;
        }

        // Parse the destination URL
        $parsedUrl = parse_url($destinationUrl);

        // Return original if URL is malformed and cannot be parsed
        if ($parsedUrl === false) {
            return $destinationUrl;
        }

        // Get existing query params from destination
        $existingParams = [];
        if (!empty($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $existingParams);
        }

        // Merge params - incoming params take precedence (override existing)
        $mergedParams = array_merge($existingParams, $incomingParams);

        // Rebuild the URL
        $scheme = !empty($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
        $auth = '';
        if (!empty($parsedUrl['user'])) {
            $auth = $parsedUrl['user'];
            if (!empty($parsedUrl['pass'])) {
                $auth .= ':' . $parsedUrl['pass'];
            }
            $auth .= '@';
        }
        $host = $parsedUrl['host'] ?? '';
        $port = !empty($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $path = $parsedUrl['path'] ?? '';
        $fragment = !empty($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

        // Build query string (mergedParams is never empty since we return early if no incoming params)
        $queryString = '?' . http_build_query($mergedParams);

        // Handle protocol-relative URLs (starting with //)
        if (str_starts_with($destinationUrl, '//')) {
            return '//' . $auth . $host . $port . $path . $queryString . $fragment;
        }

        // Handle relative URLs (starting with /)
        if (empty($scheme) && str_starts_with($destinationUrl, '/')) {
            return $path . $queryString . $fragment;
        }

        return $scheme . $auth . $host . $port . $path . $queryString . $fragment;
    }

    /**
     * Sanitize a URL to prevent XSS via dangerous schemes.
     *
     * Only allows http://, https://, and relative paths (starting with /).
     * Rejects javascript:, data:, vbscript:, and other dangerous schemes.
     *
     * @param string $url
     * @return string Sanitized URL, or '/' if scheme is disallowed
     */
    private function _sanitizeUrl(string $url): string
    {
        if (!UrlSafetyHelper::isSafeRedirectUrl($url)) {
            $this->logWarning('Blocked unsafe URL scheme', ['url' => $url]);
        }

        return UrlSafetyHelper::sanitizeRedirectUrl($url);
    }

    /**
     * Sanitize a message string for safe template rendering.
     *
     * Strips HTML tags to prevent XSS even if user templates render with |raw.
     *
     * @param string $message
     * @return string Plain text message
     */
    private function _sanitizeMessage(string $message): string
    {
        return strip_tags($message);
    }

    private function applyNoStoreHeaders(Response $response): void
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
    }
}
