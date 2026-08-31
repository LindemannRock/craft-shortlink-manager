<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Fixtures\Http;

use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\services\ShortLinksService;

/**
 * Supplies a disposable HTTP fixture where one site lacks a code that exists on another site.
 *
 * @since 5.28.4
 */
final class HttpGraphqlScopedShortLinksService extends ShortLinksService
{
    public function __construct(
        private readonly int $linkId,
        private readonly string $code,
        private readonly int $siteWithoutMatch,
        private readonly int $matchedSiteId,
    ) {
        parent::__construct();
    }

    public function getByCode(string $code, ?int $siteId = null): ?ShortLink
    {
        if ($code !== $this->code || $siteId === $this->siteWithoutMatch) {
            return null;
        }

        if ($siteId !== null && $siteId !== $this->matchedSiteId) {
            return null;
        }

        return ShortLink::find()
            ->id($this->linkId)
            ->siteId($this->matchedSiteId)
            ->status(null)
            ->one();
    }
}
