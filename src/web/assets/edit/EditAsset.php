<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\web\assets\edit;

use craft\web\AssetBundle;

/**
 * ShortLink edit asset bundle.
 *
 * Provides client-side behavior for the ShortLink edit form.
 *
 * @since 5.22.0
 */
class EditAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->js = [
            'edit.js',
        ];

        parent::init();
    }
}
