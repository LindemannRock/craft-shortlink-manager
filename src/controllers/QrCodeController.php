<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\models\Site;
use craft\web\Controller;
use lindemannrock\base\helpers\AssetVolumeHelper;
use lindemannrock\base\helpers\SafeSegmentHelper;
use lindemannrock\base\helpers\UrlSafetyHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

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
     * @throws ServerErrorHttpException
     */
    public function actionGenerate(?string $code = null, ?string $siteHandle = null): Response
    {
        $request = Craft::$app->request;
        $settings = ShortLinkManager::$plugin->getSettings();
        $isSettingsPreview = $code === null
            && $this->queryFlag('preview')
            && is_string($request->getQueryParam('url'));
        $linkId = $code === null ? $this->queryScalar('linkId') : null;
        $isExistingLinkMode = $linkId !== null;
        $isAuthenticatedMode = $isSettingsPreview || $isExistingLinkMode;
        $isDownload = $this->queryFlag('download');

        if ($isAuthenticatedMode) {
            $this->requireLogin();
            $this->requirePermission('shortLinkManager:editLinks');
        }

        if ($isSettingsPreview) {
            $url = (string)$request->getQueryParam('url');
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (!in_array(strtolower($scheme ?? ''), ['http', 'https'], true)) {
                throw new BadRequestHttpException('Only http and https URLs are allowed.');
            }
            if ($isDownload && !$settings->enableQrDownload) {
                throw new NotFoundHttpException('Short link not found.');
            }

            $fullUrl = $url;
            $shortLink = null;
            $options = $this->authenticatedOptions();
            if ($isDownload) {
                $options['size'] = $this->normalizeExportSize($this->queryScalar('size'), (int)$settings->defaultQrSize);
                $options['_sizeMax'] = 4096;
            }
            $options['_cache'] = false;
        } elseif ($isExistingLinkMode) {
            $shortLink = $this->resolveExistingLink($linkId, $this->queryScalar('siteId'));
            if (!$settings->isSiteEnabled($shortLink->siteId) || !$shortLink->qrCodeEnabled) {
                throw new NotFoundHttpException('Short link not found.');
            }
            if ($isDownload && !$settings->enableQrDownload) {
                throw new NotFoundHttpException('Short link not found.');
            }

            $fullUrl = $this->trackedUrl($shortLink);
            $options = $this->authenticatedOptions($this->canonicalOptions($shortLink));
            $options['size'] = $isDownload
                ? $this->normalizeExportSize($this->queryScalar('size'), (int)$shortLink->qrCodeSize)
                : 150;
            $options['_cache'] = false;
            $options['_sizeMax'] = 4096;
        } else {
            if ($code === null || trim($code) === '') {
                throw new BadRequestHttpException('Link ID, code, or preview URL required');
            }

            $shortLink = $this->resolvePublicLink($code, $siteHandle);
            if (!$settings->isSiteEnabled($shortLink->siteId)) {
                $redirectUrl = UrlSafetyHelper::sanitizeRedirectUrl($settings->getResolvedNotFoundRedirectUrl());
                return $this->redirect($redirectUrl);
            }
            if (!$shortLink->qrCodeEnabled) {
                $redirectUrl = UrlSafetyHelper::sanitizeRedirectUrl($settings->getResolvedNotFoundRedirectUrl());
                return $this->redirect($redirectUrl);
            }

            $fullUrl = $this->trackedUrl($shortLink);
            $options = $this->canonicalOptions($shortLink);
        }

        $format = ShortLinkManager::$plugin->qrCode->normalizeFormat($options['format'] ?? null);
        $options['format'] = $format;

        $mode = $isSettingsPreview ? 'preview' : ($isExistingLinkMode ? 'cp-link' : 'public-code');
        $this->logDebug('Generating QR code', [
            'mode' => $mode,
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

            $contentType = $format === 'svg' ? 'image/svg+xml' : 'image/png';

            // Return response
            $response = Craft::$app->response;
            $response->format = Response::FORMAT_RAW;
            $response->headers->set('Content-Type', $contentType);
            $response->headers->set(
                'Cache-Control',
                $isAuthenticatedMode ? 'private, no-store, no-cache, must-revalidate, max-age=0' : 'public, max-age=86400',
            );
            if ($isAuthenticatedMode) {
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', '0');
            }

            if ($isDownload && $settings->enableQrDownload) {
                $filename = strtr($settings->qrDownloadFilename ?? '{code}-qr-{size}', [
                    '{code}' => $shortLink?->code ?? 'qr-code',
                    '{size}' => (string)$options['size'],
                    '{format}' => $format,
                ]);
                $filename = SafeSegmentHelper::filenamePart($filename, 'qr-code', [
                    'allowDots' => true,
                ]);
                $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.' . $format . '"');
            }

            $response->content = $qrCode;

            return $response;
        } catch (\Throwable $e) {
            $this->logError('Failed to generate QR code', [
                'mode' => $mode,
                'code' => $code ?? 'N/A',
                'linkId' => $linkId ?? 'N/A',
                'format' => $format,
                'error' => $e->getMessage(),
            ]);

            throw new ServerErrorHttpException('QR code generation failed.');
        }
    }

    /**
     * Return the saved QR configuration used by the anonymous image route.
     *
     * @return array<string, mixed>
     */
    private function canonicalOptions(ShortLink $shortLink): array
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $options = [
            'size' => max(100, min(1000, (int)($shortLink->qrCodeSize ?: $settings->defaultQrSize))),
            'color' => str_replace('#', '', $shortLink->qrCodeColor ?: $settings->defaultQrColor),
            'bg' => str_replace('#', '', $shortLink->qrCodeBgColor ?: $settings->defaultQrBgColor),
            'format' => ShortLinkManager::$plugin->qrCode->normalizeFormat($shortLink->qrCodeFormat ?: $settings->defaultQrFormat),
            'errorCorrection' => $settings->defaultQrErrorCorrection,
            'margin' => $settings->defaultQrMargin,
            'moduleStyle' => $settings->qrModuleStyle,
            'eyeStyle' => $settings->qrEyeStyle,
            'eyeColor' => $shortLink->qrCodeEyeColor
                ? str_replace('#', '', $shortLink->qrCodeEyeColor)
                : ($settings->qrEyeColor ? str_replace('#', '', $settings->qrEyeColor) : null),
        ];

        if ($settings->enableQrLogo) {
            $logoId = $shortLink->qrLogoId ?: $settings->defaultQrLogoId;
            if ($logoId) {
                $options['logo'] = $logoId;
            }
        }

        return array_filter($options, static fn(mixed $value): bool => $value !== null);
    }

    /**
     * Overlay authenticated scalar request styling on the supplied defaults.
     *
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private function authenticatedOptions(array $defaults = []): array
    {
        $options = $defaults;
        foreach (['size', 'color', 'bg', 'format', 'margin', 'moduleStyle', 'eyeStyle', 'eyeColor', 'logoSize', 'errorCorrection'] as $name) {
            $value = $this->queryScalar($name);
            if ($value !== null) {
                $options[$name] = $value;
            }
        }

        $queryParams = Craft::$app->getRequest()->getQueryParams();
        if (array_key_exists('logo', $queryParams)) {
            $logoId = $this->validatePreviewLogoId(
                $queryParams['logo'],
                ShortLinkManager::$plugin->getSettings()->qrLogoVolumeUid,
            );
            if ($logoId === null) {
                unset($options['logo']);
            } else {
                $options['logo'] = $logoId;
            }
        }

        return array_filter($options, static fn(mixed $value): bool => $value !== null);
    }

    private function queryScalar(string $name): string|int|float|bool|null
    {
        $value = Craft::$app->getRequest()->getQueryParam($name);

        return is_scalar($value) ? $value : null;
    }

    private function queryFlag(string $name): bool
    {
        $value = $this->queryScalar($name);

        return $value !== null && !in_array($value, ['', '0', 0, false], true);
    }

    private function normalizeExportSize(string|int|float|bool|null $size, int $fallback): int
    {
        if (!is_numeric($size)) {
            $size = $fallback;
        }

        return max(100, min(4096, (int)$size));
    }

    private function resolveExistingLink(string|int|float|bool $linkId, string|int|float|bool|null $siteId): ShortLink
    {
        if (!is_numeric($linkId) || (int)$linkId < 1) {
            throw new NotFoundHttpException('Short link not found.');
        }

        $query = ShortLink::find()
            ->id((int)$linkId)
            ->status(null);
        if ($siteId !== null) {
            if (!is_numeric($siteId) || (int)$siteId < 1) {
                throw new NotFoundHttpException('Short link not found.');
            }
            $query->siteId((int)$siteId);
        } else {
            $query->site('*');
        }

        $shortLink = $query->one();
        if (!$shortLink instanceof ShortLink || $shortLink->trashed) {
            throw new NotFoundHttpException('Short link not found.');
        }

        return $shortLink;
    }

    private function resolvePublicLink(string $code, ?string $siteHandle): ShortLink
    {
        $site = $this->resolveSite($siteHandle);
        if (!$site) {
            throw new NotFoundHttpException('Site not found.');
        }

        $shortLink = ShortLinkManager::$plugin->shortLinks->getByCode($code, $site->id);
        if (!$shortLink && ($siteHandle === null || $siteHandle === '')) {
            $shortLink = ShortLinkManager::$plugin->shortLinks->getByCode($code, null);
        }
        if (!$shortLink instanceof ShortLink || $shortLink->trashed) {
            throw new NotFoundHttpException('Short link not found.');
        }

        return $shortLink;
    }

    private function trackedUrl(ShortLink $shortLink): string
    {
        $url = $shortLink->getUrl();
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'src=qr';
    }

    /**
     * Validate a submitted preview logo against its configured volume and permission.
     */
    protected function validatePreviewLogoId(mixed $logoId, ?string $allowedVolumeUid): ?int
    {
        return AssetVolumeHelper::validateAssetId($logoId, $allowedVolumeUid);
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
        if (!$shortLink && ($siteHandle === null || $siteHandle === '')) {
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
            'siteName' => $site->name,
            'currentSite' => $site,
        ];

        ShortLinkManager::$plugin->integration->prepareSeomaticMetadata($shortLink);

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
     * Resolve request site from an exact route identifier, otherwise use the
     * current site.
     */
    private function resolveSite(?string $siteHandle): ?Site
    {
        if ($siteHandle) {
            $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
            if ($site !== null) {
                return $site;
            }

            if (ctype_digit($siteHandle) && (string)(int)$siteHandle === $siteHandle) {
                $site = Craft::$app->getSites()->getSiteById((int)$siteHandle);
                if ($site !== null) {
                    return $site;
                }
            }

            try {
                return Craft::$app->getSites()->getSiteByUid($siteHandle);
            } catch (SiteNotFoundException) {
                return null;
            }
        }

        return Craft::$app->getSites()->getCurrentSite();
    }
}
