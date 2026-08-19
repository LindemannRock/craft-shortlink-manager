<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Eye\PointyEye;
use BaconQrCode\Renderer\Eye\SimpleCircleEye;
use BaconQrCode\Renderer\Eye\SquareEye;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Module\DotsModule;
use BaconQrCode\Renderer\Module\RoundnessModule;
use BaconQrCode\Renderer\Module\SquareModule;
use BaconQrCode\Renderer\RendererStyle\EyeFill;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use craft\base\Component;
use craft\elements\Asset;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\helpers\QrCodeRendererHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * QR Code Service
 *
 * @since 5.0.0
 */
class QrCodeService extends Component
{
    use LoggingTrait;

    private const FORMAT_PNG = 'png';
    private const FORMAT_SVG = 'svg';
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";
    private const PNG_IEND_CHUNK = "\x00\x00\x00\x00IEND\xAE\x42\x60\x82";

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
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

        // Merge options with defaults and clamp values
        $size = max(100, min(1000, (int)($options['size'] ?? $settings->defaultQrSize)));
        $color = $this->normalizeHexColor($options['color'] ?? null, (string)$settings->defaultQrColor);
        $bgColor = $this->normalizeHexColor($options['bg'] ?? $options['backgroundColor'] ?? null, (string)$settings->defaultQrBgColor);
        $format = $this->normalizeFormat($options['format'] ?? null);
        $margin = max(0, min(10, (int)($options['margin'] ?? $settings->defaultQrMargin)));
        $errorCorrection = $this->normalizeErrorCorrection(
            $options['errorCorrection'] ?? null,
            $settings->defaultQrErrorCorrection,
        );
        $moduleStyle = in_array($options['moduleStyle'] ?? $settings->qrModuleStyle, ['square', 'dots', 'rounded'], true)
            ? ($options['moduleStyle'] ?? $settings->qrModuleStyle)
            : $settings->qrModuleStyle;
        $eyeStyle = in_array($options['eyeStyle'] ?? $settings->qrEyeStyle, ['square', 'rounded', 'pointed'], true)
            ? ($options['eyeStyle'] ?? $settings->qrEyeStyle)
            : $settings->qrEyeStyle;
        $eyeColor = $this->normalizeOptionalHexColor($options['eyeColor'] ?? null, $settings->qrEyeColor ?? null);
        $logoId = $options['logo'] ?? null;
        $logoSize = max(10, min(30, (int)($options['logoSize'] ?? $settings->qrLogoSize ?? 20)));

        // Create cache key including new style parameters and logo
        $cacheKey = $this->_getCacheKey($url, $size, $color, $bgColor, $format, $margin, $errorCorrection, $moduleStyle, $eyeStyle, $eyeColor, $logoId, $logoSize);

        // Check the configured disposable cache (if caching is enabled).
        if ($settings->enableQrCodeCache) {
            try {
                $cached = ShortLinkManager::$plugin->cacheStorage->readQrCode(
                    $cacheKey,
                    $settings->qrCodeCacheDuration,
                );
                if ($cached->isHit() && is_string($cached->value)) {
                    if ($this->isValidOutput($cached->value, $format)) {
                        return $cached->value;
                    }

                    $this->logWarning('Ignored invalid cached QR code output', [
                        'format' => $format,
                        'cacheKey' => $cacheKey,
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logWarning('Failed to read QR code cache', [
                    'format' => $format,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Generate QR code
        try {
            $qrCode = $this->_generateQrCode($url, $size, $color, $bgColor, $format, $margin, $errorCorrection, $moduleStyle, $eyeStyle, $eyeColor, $logoId, $logoSize);
        } catch (\Throwable $e) {
            $this->logError('Failed to render QR code', [
                'format' => $format,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if (!$this->isValidOutput($qrCode, $format)) {
            $this->logError('QR renderer returned invalid output', [
                'format' => $format,
                'length' => strlen($qrCode),
            ]);
            throw new \RuntimeException('QR renderer returned invalid output.');
        }

        // Cache the result (if caching enabled)
        if ($settings->enableQrCodeCache) {
            try {
                if (!ShortLinkManager::$plugin->cacheStorage->writeQrCode(
                    $cacheKey,
                    $qrCode,
                    $settings->qrCodeCacheDuration,
                )) {
                    $this->logWarning('Failed to cache generated QR code', [
                        'format' => $format,
                        'cacheKey' => $cacheKey,
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logWarning('Failed to write QR code cache', [
                    'format' => $format,
                    'error' => $e->getMessage(),
                ]);
            }
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
        $format = $this->normalizeFormat($options['format'] ?? null);
        $options['format'] = $format;
        $qrCode = $this->generateQrCode($url, $options);

        $mimeType = $format === self::FORMAT_SVG ? 'image/svg+xml' : 'image/png';
        $encoded = base64_encode($qrCode);

        return "data:$mimeType;base64,$encoded";
    }

    /**
     * Normalize a requested QR output format against the effective setting.
     *
     * @since 5.29.0
     */
    public function normalizeFormat(mixed $format): string
    {
        $defaultFormat = ShortLinkManager::$plugin->getSettings()->defaultQrFormat;
        if (!in_array($defaultFormat, [self::FORMAT_PNG, self::FORMAT_SVG], true)) {
            $defaultFormat = self::FORMAT_PNG;
        }

        return in_array($format, [self::FORMAT_PNG, self::FORMAT_SVG], true)
            ? $format
            : $defaultFormat;
    }

    /**
     * Generate cache key for QR code
     */
    private function _getCacheKey(string $url, int $size, string $color, string $bgColor, string $format, int $margin, string $errorCorrection, string $moduleStyle, string $eyeStyle, ?string $eyeColor, ?string $logoId, int $logoSize): string
    {
        return PluginHelper::getCacheKeyPrefix(ShortLinkManager::$plugin->id, 'qr') . md5(implode(':', [
            $url,
            $size,
            $color,
            $bgColor,
            $format,
            $margin,
            $errorCorrection,
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
    protected function _generateQrCode(string $url, int $size, string $color, string $bgColor, string $format, int $margin, string $errorCorrection, string $moduleStyle, string $eyeStyle, ?string $eyeColor, ?string $logoId, int $logoSize): string
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

        if ($format === self::FORMAT_SVG) {
            // SVG format
            $renderer = new ImageRenderer(
                $rendererStyle,
                new SvgImageBackEnd()
            );
        } else {
            $renderer = QrCodeRendererHelper::createPngRenderer($rendererStyle);
        }

        // Create writer
        $writer = new Writer($renderer);

        // Generate QR code
        $qrCode = $writer->writeString(
            $url,
            Encoder::DEFAULT_BYTE_MODE_ENCODING,
            $this->errorCorrectionLevel($errorCorrection),
        );

        // Add logo overlay if specified and not SVG format
        if ($logoId && $format !== self::FORMAT_SVG) {
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
                return SimpleCircleEye::instance();
            case 'pointed':
                return PointyEye::instance();
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

    private function normalizeHexColor(mixed $value, string $fallback): string
    {
        $normalized = $this->normalizeOptionalHexColor($value, $fallback);

        return $normalized ?? '000000';
    }

    private function normalizeOptionalHexColor(mixed $value, mixed $fallback = null): ?string
    {
        foreach ([$value, $fallback] as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }

            $color = ltrim(trim((string)$candidate), '#');
            if (preg_match('/^[0-9A-Fa-f]{6}$/', $color) === 1) {
                return strtoupper($color);
            }
        }

        return null;
    }

    private function normalizeErrorCorrection(mixed $value, mixed $configuredDefault): string
    {
        $default = $this->normalizeErrorCorrectionToken($configuredDefault) ?? 'M';

        return $this->normalizeErrorCorrectionToken($value) ?? $default;
    }

    private function normalizeErrorCorrectionToken(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $token = strtoupper(trim((string)$value));

        return in_array($token, ['L', 'M', 'Q', 'H'], true) ? $token : null;
    }

    private function errorCorrectionLevel(string $errorCorrection): ErrorCorrectionLevel
    {
        return match ($errorCorrection) {
            'L' => ErrorCorrectionLevel::L(),
            'Q' => ErrorCorrectionLevel::Q(),
            'H' => ErrorCorrectionLevel::H(),
            default => ErrorCorrectionLevel::M(),
        };
    }

    private function isValidOutput(string $output, string $format): bool
    {
        if ($output === '') {
            return false;
        }

        if ($format === self::FORMAT_PNG) {
            return strlen($output) >= 45
                && str_starts_with($output, self::PNG_SIGNATURE)
                && substr($output, 12, 4) === 'IHDR'
                && strpos($output, 'IDAT', 24) !== false
                && str_ends_with($output, self::PNG_IEND_CHUNK);
        }

        $svg = ltrim($output, "\xEF\xBB\xBF\x00\x09\x0A\x0D\x20");

        return preg_match('/^(?:<\?xml[^>]*>\s*)?<svg\b[^>]*>/i', $svg) === 1
            && stripos($svg, '</svg>') !== false;
    }

    /**
     * Add logo overlay to QR code
     */
    protected function _addLogoToQrCode(string $qrCodeData, string $logoId, int $qrSize, int $logoSizePercent): string
    {
        $logoPath = null;
        $qrImage = null;
        $logoImage = null;
        $resizedLogo = null;
        $bufferLevel = ob_get_level();

        try {
            // Get logo asset
            $logoAsset = $this->resolveLogoAsset($logoId);
            if (!$logoAsset) {
                $this->logWarning('QR logo asset was not found', ['logoId' => $logoId]);
                return $qrCodeData;
            }

            // Get logo file path
            try {
                $logoPath = $logoAsset->getCopyOfFile();
            } catch (\Throwable $e) {
                $this->logError('Failed to copy QR logo asset', [
                    'logoId' => $logoId,
                    'error' => $e->getMessage(),
                ]);
                return $qrCodeData;
            }
            if (!$logoPath || !file_exists($logoPath)) {
                $this->logWarning('QR logo asset copy is unavailable', ['logoId' => $logoId]);
                return $qrCodeData;
            }

            // Create QR code image from binary data
            $qrImage = imagecreatefromstring($qrCodeData);
            if (!$qrImage) {
                $this->logError('Failed to decode rendered PNG for QR logo overlay', ['logoId' => $logoId]);
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
            } else {
                $this->logWarning('QR logo asset is unreadable or corrupt', ['logoId' => $logoId]);
            }

            if (!$logoImage) {
                if ($imageInfo && !in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
                    $this->logWarning('QR logo asset format is unsupported', [
                        'logoId' => $logoId,
                        'imageType' => $imageInfo[2],
                    ]);
                } elseif ($imageInfo) {
                    $this->logWarning('Failed to decode QR logo asset', ['logoId' => $logoId]);
                }
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
            if (!$resizedLogo) {
                return $qrCodeData;
            }

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

            // Overlay logo on QR code
            imagecopy($qrImage, $resizedLogo, $logoX, $logoY, 0, 0, $logoWidth, $logoHeight);

            // Convert back to binary data
            $result = $this->encodeLogoPng($qrImage);

            return $result !== false ? $result : $qrCodeData;
        } catch (\Throwable $e) {
            $this->logError('Failed to compose QR logo overlay', [
                'logoId' => $logoId,
                'error' => $e->getMessage(),
            ]);
            return $qrCodeData;
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            $this->releaseGdImage($qrImage);
            $this->releaseGdImage($logoImage);
            $this->releaseGdImage($resizedLogo);

            if (is_string($logoPath) && is_file($logoPath)) {
                @unlink($logoPath);
            }
        }
    }

    /**
     * Encode a composed QR image as PNG.
     */
    protected function encodeLogoPng(\GdImage $image): string|false
    {
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    /**
     * Release a GD image without calling deprecated APIs on PHP 8.5+.
     */
    private function releaseGdImage(?\GdImage &$image): void
    {
        if (!$image instanceof \GdImage) {
            return;
        }

        if (PHP_VERSION_ID < 80500) {
            imagedestroy($image);
        }

        $image = null;
    }

    /**
     * Resolve the configured logo through Craft's Asset element query.
     */
    protected function resolveLogoAsset(string $logoId): ?Asset
    {
        $asset = Asset::find()->id($logoId)->one();

        return $asset instanceof Asset ? $asset : null;
    }
}
