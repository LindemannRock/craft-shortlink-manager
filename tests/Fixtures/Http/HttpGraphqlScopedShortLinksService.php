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
        private readonly string $scopePath,
    ) {
        parent::__construct();
    }

    public function getByCode(string $code, ?int $siteId = null): ?ShortLink
    {
        $scope = $this->scope();
        if (!$scope['enabled']) {
            return parent::getByCode($code, $siteId);
        }

        if ($code !== $scope['code'] || $siteId === $scope['siteWithoutMatch']) {
            return null;
        }

        if ($siteId !== null && $siteId !== $scope['matchedSiteId']) {
            return null;
        }

        return ShortLink::find()
            ->id($scope['linkId'])
            ->siteId($scope['matchedSiteId'])
            ->status(null)
            ->one();
    }

    /** @return array{enabled: bool, linkId: int, code: string, siteWithoutMatch: int, matchedSiteId: int} */
    private function scope(): array
    {
        $scope = json_decode((string)file_get_contents($this->scopePath), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($scope)) {
            throw new \RuntimeException('Invalid HTTP GraphQL scope fixture.');
        }

        return [
            'enabled' => (bool)($scope['enabled'] ?? false),
            'linkId' => (int)($scope['linkId'] ?? 0),
            'code' => (string)($scope['code'] ?? ''),
            'siteWithoutMatch' => (int)($scope['siteWithoutMatch'] ?? 0),
            'matchedSiteId' => (int)($scope['matchedSiteId'] ?? 0),
        ];
    }
}
