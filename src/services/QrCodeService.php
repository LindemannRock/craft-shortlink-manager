<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\EyeFill;
use BaconQrCode\Renderer\Module\SquareModule;
use BaconQrCode\Renderer\Module\RoundnessModule;
use BaconQrCode\Renderer\Module\DotsModule;
use BaconQrCode\Renderer\Eye\SquareEye;
use BaconQrCode\Renderer\Eye\SimpleCircleEye;
use BaconQrCode\Renderer\Eye\ModuleEye;
use BaconQrCode\Writer;
use Craft;
use craft\base\Component;
use craft\elements\Asset;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * QR Code Service
 */
class QrCodeService extends Component
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
     * Generate QR code for a URL
     *
     * @param string $url
     * @param array $options
     * @return string
     */
    public function generateQrCode(string $url, array $options = []): string
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        // Merge options with defaults
        $size = $options['size'] ?? $settings->defaultQrSize;
        $color = $options['color'] ?? $settings->defaultQrColor;
        $bgColor = $options['bg'] ?? $options['backgroundColor'] ?? $settings->defaultQrBgColor;
        $format = $options['format'] ?? $settings->defaultQrFormat;
        $margin = $options['margin'] ?? $settings->defaultQrMargin;
        $moduleStyle = $options['moduleStyle'] ?? $settings->qrModuleStyle;
        $eyeStyle = $options['eyeStyle'] ?? $settings->qrEyeStyle;
        $eyeColor = $options['eyeColor'] ?? $settings->qrEyeColor ?? null;
        $logoId = $options['logo'] ?? null;
        $logoSize = $options['logoSize'] ?? $settings->qrLogoSize ?? 20;

        // Create cache key including new style parameters and logo
        $cacheKey = $this->_getCacheKey($url, $size, $color, $bgColor, $format, $margin, $moduleStyle, $eyeStyle, $eyeColor, $logoId, $logoSize);

        // Check cache using custom file storage (if caching enabled)
        if ($settings->enableQrCodeCache) {
            $cached = $this->_getCachedQrCode($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Generate QR code
        $qrCode = $this->_generateQrCode($url, $size, $color, $bgColor, $format, $margin, $moduleStyle, $eyeStyle, $eyeColor, $logoId, $logoSize);

        // Cache the result using custom file storage (if caching enabled)
        if ($settings->enableQrCodeCache) {
            $this->_cacheQrCode($cacheKey, $qrCode, $settings->qrCodeCacheDuration);
        }

        return $qrCode;
    }

    /**
     * Generate QR code data URL
     *
     * @param string $url
     * @param array $options
     * @return string
     */
    public function generateQrCodeDataUrl(string $url, array $options = []): string
    {
        $format = $options['format'] ?? ShortLinkManager::$plugin->getSettings()->defaultQrFormat;
        $qrCode = $this->generateQrCode($url, $options);

        $mimeType = $format === 'svg' ? 'image/svg+xml' : 'image/png';
        $encoded = base64_encode($qrCode);

        return "data:$mimeType;base64,$encoded";
    }

    /**
     * Generate cache key for QR code
     */
    private function _getCacheKey(string $url, int $size, string $color, string $bgColor, string $format, int $margin, string $moduleStyle, string $eyeStyle, ?string $eyeColor, ?string $logoId, int $logoSize): string
    {
        return 'shortlinkmanager:qr:' . md5(implode(':', [
            $url,
            $size,
            $color,
            $bgColor,
            $format,
            $margin,
            $moduleStyle,
            $eyeStyle,
            $eyeColor ?? 'null',
            $logoId ?? 'null',
            $logoSize,
        ]));
    }

    /**
     * Generate QR code
     */
    private function _generateQrCode(string $url, int $size, string $color, string $bgColor, string $format, int $margin, string $moduleStyle, string $eyeStyle, ?string $eyeColor, ?string $logoId, int $logoSize): string
    {
        // Parse colors
        $foregroundColor = $this->_parseColor($color);
        $backgroundColor = $this->_parseColor($bgColor);
        $eyeForegroundColor = $eyeColor ? $this->_parseColor($eyeColor) : $foregroundColor;

        // Create module style
        $module = $this->_createModule($moduleStyle);

        // Create eye style
        $eye = $this->_createEye($eyeStyle);

        // Create fill with colors
        if ($eyeColor) {
            // Create custom eye fill if eye color is specified
            $eyeFill = EyeFill::uniform($eyeForegroundColor);
            $fill = Fill::withForegroundColor(
                $backgroundColor,
                $foregroundColor,
                $eyeFill,  // top-left eye
                $eyeFill,  // top-right eye
                $eyeFill   // bottom-left eye
            );
        } else {
            // Use uniform color for all elements
            $fill = Fill::uniformColor($backgroundColor, $foregroundColor);
        }

        // Create renderer style with advanced options
        $rendererStyle = new RendererStyle(
            $size,
            $margin,
            $module,
            $eye,
            $fill
        );

        if ($format === 'svg') {
            // SVG format
            $renderer = new ImageRenderer(
                $rendererStyle,
                new SvgImageBackEnd()
            );
        } else {
            // PNG format - using Imagick backend
            $renderer = new ImageRenderer(
                $rendererStyle,
                new \BaconQrCode\Renderer\Image\ImagickImageBackEnd()
            );
        }

        // Create writer
        $writer = new Writer($renderer);

        // Generate QR code
        $qrCode = $writer->writeString($url);

        // Add logo overlay if specified and not SVG format
        if ($logoId && $format !== 'svg') {
            $qrCode = $this->_addLogoToQrCode($qrCode, $logoId, $size, $logoSize);
        }

        return $qrCode;
    }

    /**
     * Create module style based on type
     */
    private function _createModule(string $moduleStyle): \BaconQrCode\Renderer\Module\ModuleInterface
    {
        switch ($moduleStyle) {
            case 'rounded':
                return new RoundnessModule(RoundnessModule::MEDIUM);
            case 'dots':
                return new DotsModule(DotsModule::MEDIUM);
            case 'square':
            default:
                return SquareModule::instance();
        }
    }

    /**
     * Create eye style based on type
     */
    private function _createEye(string $eyeStyle): \BaconQrCode\Renderer\Eye\EyeInterface
    {
        switch ($eyeStyle) {
            case 'rounded':
            case 'circle':
                return SimpleCircleEye::instance();
            case 'leaf':
                return new ModuleEye(new RoundnessModule(RoundnessModule::MEDIUM));
            case 'square':
            default:
                return SquareEye::instance();
        }
    }

    /**
     * Parse hex color to RGB
     */
    private function _parseColor(string $hex): Rgb
    {
        // Remove # if present
        $hex = ltrim($hex, '#');

        // Parse hex to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return new Rgb($r, $g, $b);
    }

    /**
     * Add logo overlay to QR code
     */
    private function _addLogoToQrCode(string $qrCodeData, string $logoId, int $qrSize, int $logoSizePercent): string
    {
        try {
            // Get logo asset
            $logoAsset = Asset::find()->id($logoId)->one();
            if (!$logoAsset) {
                return $qrCodeData;
            }

            // Get logo file path
            $logoPath = $logoAsset->getCopyOfFile();
            if (!$logoPath || !file_exists($logoPath)) {
                return $qrCodeData;
            }

            // Create QR code image from binary data
            $qrImage = imagecreatefromstring($qrCodeData);
            if (!$qrImage) {
                return $qrCodeData;
            }

            // Get QR dimensions
            $qrWidth = imagesx($qrImage);
            $qrHeight = imagesy($qrImage);

            // Create logo image
            $logoImage = null;
            $imageInfo = getimagesize($logoPath);
            if ($imageInfo) {
                switch ($imageInfo[2]) {
                    case IMAGETYPE_JPEG:
                        $logoImage = imagecreatefromjpeg($logoPath);
                        break;
                    case IMAGETYPE_PNG:
                        $logoImage = imagecreatefrompng($logoPath);
                        break;
                    case IMAGETYPE_GIF:
                        $logoImage = imagecreatefromgif($logoPath);
                        break;
                }
            }

            if (!$logoImage) {
                imagedestroy($qrImage);
                return $qrCodeData;
            }

            // Calculate logo size (percentage of QR code)
            $logoSize = (int)($qrWidth * ($logoSizePercent / 100));

            // Get original logo dimensions
            $logoOriginalWidth = imagesx($logoImage);
            $logoOriginalHeight = imagesy($logoImage);

            // Create resized logo maintaining aspect ratio
            $logoAspectRatio = $logoOriginalWidth / $logoOriginalHeight;
            if ($logoAspectRatio > 1) {
                $logoWidth = $logoSize;
                $logoHeight = (int)($logoSize / $logoAspectRatio);
            } else {
                $logoHeight = $logoSize;
                $logoWidth = (int)($logoSize * $logoAspectRatio);
            }

            // Create resized logo
            $resizedLogo = imagecreatetruecolor($logoWidth, $logoHeight);

            // Preserve transparency for PNG
            imagealphablending($resizedLogo, false);
            imagesavealpha($resizedLogo, true);
            $transparent = imagecolorallocatealpha($resizedLogo, 255, 255, 255, 127);
            imagefill($resizedLogo, 0, 0, $transparent);
            imagealphablending($resizedLogo, true);

            // Resize logo
            imagecopyresampled(
                $resizedLogo,
                $logoImage,
                0, 0, 0, 0,
                $logoWidth, $logoHeight,
                $logoOriginalWidth, $logoOriginalHeight
            );

            // Calculate position (center)
            $logoX = (int)(($qrWidth - $logoWidth) / 2);
            $logoY = (int)(($qrHeight - $logoHeight) / 2);

            // Add white background circle for better visibility
            $backgroundColor = imagecolorallocate($qrImage, 255, 255, 255);
            $circleRadius = (int)(max($logoWidth, $logoHeight) / 2) + 2;
            $centerX = $logoX + (int)($logoWidth / 2);
            $centerY = $logoY + (int)($logoHeight / 2);

            imagefilledellipse($qrImage, $centerX, $centerY, $circleRadius * 2, $circleRadius * 2, $backgroundColor);

            // Overlay logo on QR code
            imagecopy($qrImage, $resizedLogo, $logoX, $logoY, 0, 0, $logoWidth, $logoHeight);

            // Convert back to binary data
            ob_start();
            imagepng($qrImage);
            $result = ob_get_contents();
            ob_end_clean();

            // Clean up
            imagedestroy($qrImage);
            imagedestroy($logoImage);
            imagedestroy($resizedLogo);

            // Clean up temporary file
            if (file_exists($logoPath)) {
                unlink($logoPath);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logError('Failed to add logo to QR code', ['error' => $e->getMessage()]);
            return $qrCodeData;
        }
    }

    /**
     * Get cached QR code from custom file storage
     */
    private function _getCachedQrCode(string $cacheKey): ?string
    {
        $cachePath = Craft::$app->path->getRuntimePath() . '/shortlink-manager/cache/qr/';
        $cacheFile = $cachePath . md5($cacheKey) . '.cache';

        if (!file_exists($cacheFile)) {
            return null;
        }

        // Check if cache is expired
        $mtime = filemtime($cacheFile);
        $settings = ShortLinkManager::$plugin->getSettings();
        if (time() - $mtime > $settings->qrCodeCacheDuration) {
            @unlink($cacheFile);
            return null;
        }

        return file_get_contents($cacheFile);
    }

    /**
     * Cache QR code to custom file storage
     */
    private function _cacheQrCode(string $cacheKey, string $data, int $duration): void
    {
        $cachePath = Craft::$app->path->getRuntimePath() . '/shortlink-manager/cache/qr/';

        // Create directory if it doesn't exist
        if (!is_dir($cachePath)) {
            \craft\helpers\FileHelper::createDirectory($cachePath);
        }

        $cacheFile = $cachePath . md5($cacheKey) . '.cache';
        file_put_contents($cacheFile, $data);
    }
}
