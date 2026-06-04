<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\controllers;

use Craft;
use craft\models\Site;
use craft\web\Controller;
use lindemannrock\base\helpers\AssetVolumeHelper;
use lindemannrock\base\helpers\SafeSegmentHelper;
use lindemannrock\base\helpers\UrlSafetyHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * QR Code Controller
 *
 * @since 5.0.0
 */
class QrCodeController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = true;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
    }

    /**
     * Generate QR code for short link
     *
     * @param string|null $code Short link code from URL route
     * @param string|null $siteHandle Site handle from route (for site-aware short domains)
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionGenerate(?string $code = null, ?string $siteHandle = null): Response
    {
        $request = Craft::$app->request;
        $settings = ShortLinkManager::$plugin->getSettings();

        // Check if this is a preview request (from CP)
        $isPreview = $request->getQueryParam('preview');
        $url = $request->getQueryParam('url');
        $linkId = $request->getQueryParam('linkId');

        if ($isPreview && $url) {
            // Preview mode requires login and edit permission (CP feature only)
            $this->requireLogin();
            $this->requirePermission('shortLinkManager:editLinks');

            // Validate URL scheme (prevent javascript:, data:, etc.)
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (!in_array(strtolower($scheme ?? ''), ['http', 'https'], true)) {
                throw new \yii\web\BadRequestHttpException('Only http and https URLs are allowed.');
            }

            // Preview mode - generate QR code for any URL
            $fullUrl = $url;
            $shortLink = null;
        } elseif ($linkId) {
            // CP mode - get by link ID (requires login + edit permission)
            $this->requireLogin();
            $this->requirePermission('shortLinkManager:editLinks');

            $shortLink = ShortLink::find()
                ->id($linkId)
                ->status(null)
                ->one();

            if (!$shortLink) {
                throw new NotFoundHttpException('Short link not found.');
            }

            // Check if link is trashed
            if ($shortLink->trashed) {
                throw new NotFoundHttpException('Short link not found.');
            }

            // Generate full URL for the short link with QR tracking parameter
            $url = $shortLink->getUrl();
            $separator = strpos($url, '?') !== false ? '&' : '?';
            $fullUrl = $url . $separator . 'src=qr';
        } elseif ($code) {
            // Frontend mode - get by code from URL route
            $site = $this->resolveSite($siteHandle);
            if (!$site) {
                throw new NotFoundHttpException('Site not found.');
            }
            $shortLink = ShortLinkManager::$plugin->shortLinks->getByCode($code, $site->id);

            // Fallback: on custom domains, current-site resolution can differ from link site.
            // If a code exists on another enabled site, still allow QR generation.
            if (!$shortLink) {
                $shortLink = ShortLinkManager::$plugin->shortLinks->getByCode($code, null);
            }

            if (!$shortLink) {
                throw new NotFoundHttpException('Short link not found.');
            }

            // Check if link is trashed
            if ($shortLink->trashed) {
                throw new NotFoundHttpException('Short link not found.');
            }

            // Check if plugin is enabled for this site
            if (!$settings->isSiteEnabled($shortLink->siteId)) {
                $redirectUrl = UrlSafetyHelper::sanitizeRedirectUrl($settings->getResolvedNotFoundRedirectUrl());
                return $this->redirect($redirectUrl);
            }

            // Check if QR codes are enabled for this shortlink
            if (!$shortLink->qrCodeEnabled) {
                // If QR is disabled, redirect to 404 redirect URL (consistent with shortlink behavior)
                $redirectUrl = UrlSafetyHelper::sanitizeRedirectUrl($settings->getResolvedNotFoundRedirectUrl());
                return $this->redirect($redirectUrl);
            }

            // Generate full URL for the short link with QR tracking parameter
            $url = $shortLink->getUrl();
            $separator = strpos($url, '?') !== false ? '&' : '?';
            $fullUrl = $url . $separator . 'src=qr';
        } else {
            throw new \yii\web\BadRequestHttpException('Link ID, code, or preview URL required');
        }

        // Get parameters with defaults from shortlink if available
        if ($shortLink) {
            // Use shortlink's configured QR settings
            $options = [
                'size' => $request->getQueryParam('size', $shortLink->qrCodeSize),
                'color' => $request->getQueryParam('color', $shortLink->qrCodeColor ? str_replace('#', '', $shortLink->qrCodeColor) : str_replace('#', '', $settings->defaultQrColor)),
                'bg' => $request->getQueryParam('bg', $shortLink->qrCodeBgColor ? str_replace('#', '', $shortLink->qrCodeBgColor) : str_replace('#', '', $settings->defaultQrBgColor)),
                'format' => $request->getQueryParam('format', $shortLink->qrCodeFormat ?: $settings->defaultQrFormat),
                'eyeColor' => $request->getQueryParam('eyeColor', $shortLink->qrCodeEyeColor ? str_replace('#', '', $shortLink->qrCodeEyeColor) : ($settings->qrEyeColor ? str_replace('#', '', $settings->qrEyeColor) : null)),
                'margin' => $request->getQueryParam('margin', $settings->defaultQrMargin),
                'moduleStyle' => $request->getQueryParam('moduleStyle', $settings->qrModuleStyle),
                'eyeStyle' => $request->getQueryParam('eyeStyle', $settings->qrEyeStyle),
            ];

            // Add logo if enabled
            if ($settings->enableQrLogo) {
                $logoId = $shortLink->qrLogoId ?: $settings->defaultQrLogoId;
                if ($logoId) {
                    $options['logo'] = $logoId;
                }
            }
        } else {
            // Preview mode - just use query params. Validate the logo server-side
            // against the configured volume + the user's viewAssets permission.
            $logoId = AssetVolumeHelper::validateAssetId(
                $request->getQueryParam('logo'),
                $settings->qrLogoVolumeUid,
            );

            $options = [
                'size' => $request->getQueryParam('size'),
                'color' => $request->getQueryParam('color'),
                'bg' => $request->getQueryParam('bg'),
                'format' => $request->getQueryParam('format'),
                'logo' => $logoId,
                'eyeColor' => $request->getQueryParam('eyeColor'),
            ];
        }

        // Remove null values
        $options = array_filter($options, fn($value) => $value !== null);

        // Debug logging
        $this->logInfo('Generating QR code', [
            'code' => $code ?? 'N/A',
            'linkId' => $linkId ?? 'N/A',
            'fullUrl' => $fullUrl,
            'options' => $options,
            'hasLogo' => isset($options['logo']),
            'enableQrLogo' => $settings->enableQrLogo,
            'shortLinkLogoId' => $shortLink ? $shortLink->qrLogoId : 'N/A',
        ]);

        // Generate QR code
        try {
            $qrCode = ShortLinkManager::$plugin->qrCode->generateQrCode($fullUrl, $options);

            // Determine content type (validate format to known values)
            $format = in_array($options['format'] ?? '', ['png', 'svg'], true)
                ? $options['format']
                : ShortLinkManager::$plugin->getSettings()->defaultQrFormat;
            $contentType = $format === 'svg' ? 'image/svg+xml' : 'image/png';

            // Return response
            $response = Craft::$app->response;
            $response->format = Response::FORMAT_RAW;
            $response->headers->set('Content-Type', $contentType);
            $response->headers->set('Cache-Control', 'public, max-age=86400');

            // Handle download request
            if ($request->getQueryParam('download') && $shortLink && $settings->enableQrDownload) {
                $filename = strtr($settings->qrDownloadFilename ?? '{slug}-qr-{size}', [
                    '{slug}' => $shortLink->slug,
                    '{code}' => $shortLink->code,
                    '{size}' => $options['size'] ?? $settings->defaultQrSize,
                    '{format}' => $format,
                ]);
                $filename = SafeSegmentHelper::filenamePart($filename, 'qr-code', [
                    'allowDots' => true,
                ]);
                $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.' . $format . '"');
            }

            $response->content = $qrCode;

            return $response;
        } catch (\Exception $e) {
            throw new NotFoundHttpException('Failed to generate QR code.');
        }
    }

    /**
     * Display QR code page for short link
     *
     * @param string $code
     * @param string|null $siteHandle
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionDisplay(string $code, ?string $siteHandle = null): Response
    {
        // Get the short link
        $site = $this->resolveSite($siteHandle);
        if (!$site) {
            throw new NotFoundHttpException('Site not found.');
        }
        $shortLink = ShortLinkManager::$plugin->shortLinks->getByCode($code, $site->id);

        // Fallback: resolve by code across sites if current-site lookup misses.
        if (!$shortLink) {
            $shortLink = ShortLinkManager::$plugin->shortLinks->getByCode($code, null);
        }

        if (!$shortLink) {
            throw new NotFoundHttpException('Short link not found.');
        }

        // Get settings
        $settings = ShortLinkManager::$plugin->getSettings();

        // Check if link is trashed
        if ($shortLink->trashed) {
            throw new NotFoundHttpException('Short link not found.');
        }

        // Check if plugin is enabled for this site
        if (!$settings->isSiteEnabled($shortLink->siteId)) {
            $redirectUrl = UrlSafetyHelper::sanitizeRedirectUrl($settings->getResolvedNotFoundRedirectUrl());
            return $this->redirect($redirectUrl);
        }

        // If QR is disabled, redirect to 404 redirect URL (consistent with shortlink behavior)
        if (!$shortLink->qrCodeEnabled) {
            $redirectUrl = UrlSafetyHelper::sanitizeRedirectUrl($settings->getResolvedNotFoundRedirectUrl());
            return $this->redirect($redirectUrl);
        }

        // Get template setting
        $template = $settings->qrTemplate ?: 'shortlink-manager/qr';

        // Prepare template variables
        $templateVars = [
            'shortLink' => $shortLink,
            'siteName' => Craft::$app->sites->getCurrentSite()->name,
            'currentSite' => Craft::$app->sites->getCurrentSite(),
        ];

        // Render the template
        try {
            return $this->renderTemplate($template, $templateVars);
        } catch (\Exception $e) {
            $this->logError('Failed to render QR template', [
                'template' => $template,
                'code' => $code,
                'error' => $e->getMessage(),
            ]);

            // Fallback to plugin template if custom template fails
            if ($template !== 'shortlink-manager/qr') {
                return $this->renderTemplate('shortlink-manager/qr', $templateVars);
            }

            throw new NotFoundHttpException('Failed to render QR code page.');
        }
    }

    /**
     * Resolve request site from route handle (if provided), otherwise use current site.
     */
    private function resolveSite(?string $siteHandle): ?Site
    {
        if ($siteHandle) {
            return Craft::$app->getSites()->getSiteByHandle($siteHandle);
        }

        return Craft::$app->getSites()->getCurrentSite();
    }
}
