<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\web\assets\qrpreview;

use craft\web\AssetBundle;

/**
 * ShortLink QR preview asset bundle.
 *
 * Provides shared live QR preview behavior for QR settings and edit pages.
 *
 * @since 5.22.0
 */
class QrPreviewAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->js = [
            'qr-preview.js',
        ];

        parent::init();
    }
}
