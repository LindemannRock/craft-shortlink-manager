<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\integrations\seomatic;

use craft\base\Model;

/**
 * Minimal SEOmatic site settings model for the synthetic ShortLinks source.
 *
 * @since 5.22.0
 */
class ShortLinkSeoSourceSiteSettings extends Model
{
    public int $siteId;
    public bool $hasUrls = true;
    public string $template = '';
}
