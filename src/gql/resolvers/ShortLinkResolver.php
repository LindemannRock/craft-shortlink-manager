<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\gql\resolvers;

use Craft;
use craft\gql\base\Resolver;
use GraphQL\Type\Definition\ResolveInfo;
use lindemannrock\base\helpers\GqlHelper;
use lindemannrock\base\helpers\UrlSafetyHelper;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * GraphQL resolver for ShortLink Manager shortlinks.
 *
 * @since 5.21.0
 */
class ShortLinkResolver extends Resolver
{
    /**
     * Resolve a shortlink code through ShortLink Manager.
     *
     * @inheritdoc
     */
    public static function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $siteId = self::resolveRequestedSiteId($arguments);
        if ($siteId === null) {
            return null;
        }

        $code = trim((string)($arguments['code'] ?? ''));
        if ($code === '') {
            return null;
        }

        $shortLink = self::findShortLink($code, $siteId);
        if ($shortLink === null || !self::isUsable($shortLink)) {
            return null;
        }

        $destinationUrl = self::resolveDestinationUrl($shortLink);

        if ($shortLink->isExpired()) {
            return self::toArray($shortLink, self::sanitizeNullableRedirectUrl($shortLink->expiredRedirectUrl));
        }

        if ($destinationUrl === null || $destinationUrl === '') {
            return null;
        }

        if (self::shouldPassQueryParams($shortLink)) {
            $destinationUrl = self::mergeQueryString($destinationUrl, (string)($arguments['queryString'] ?? ''));
        }

        $destinationUrl = UrlSafetyHelper::sanitizeRedirectUrl($destinationUrl);

        if ($shortLink->trackAnalytics && ShortLinkManager::$plugin->getSettings()->enableAnalytics) {
            ShortLinkManager::$plugin->analytics->trackClick($shortLink, Craft::$app->getRequest(), 'graphql');
        }

        ShortLinkManager::$plugin->shortLinks->incrementHits($shortLink);

        return self::toArray($shortLink, $destinationUrl);
    }

    /**
     * List enabled shortlinks for the requested site.
     *
     * @inheritdoc
     */
    public static function resolveAll(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $siteId = self::resolveRequestedSiteId($arguments);
        if ($siteId === null) {
            return [];
        }

        $limit = $arguments['limit'] ?? null;
        $limit = is_numeric($limit) && (int)$limit > 0 ? (int)$limit : 100;

        $query = ShortLink::find()
            ->siteId($siteId)
            ->status(ShortLink::STATUS_ENABLED)
            ->orderBy(['elements.dateCreated' => SORT_DESC]);

        $query->limit(min($limit, 500));

        return array_map(
            static fn(ShortLink $shortLink): array => self::toArray($shortLink, self::sanitizeNullableRedirectUrl(self::resolveDestinationUrl($shortLink))),
            $query->all(),
        );
    }

    /**
     * Resolve site arguments using the current site as fallback.
     *
     * @param array<string, mixed> $arguments
     * @return int|null
     */
    private static function resolveRequestedSiteId(array $arguments): ?int
    {
        return GqlHelper::resolveSiteId(
            $arguments,
            Craft::$app->getSites()->getCurrentSite()->id,
        );
    }

    private static function findShortLink(string $code, int $siteId): ?ShortLink
    {
        return ShortLinkManager::$plugin->shortLinks->getByCode($code, $siteId);
    }

    private static function isUsable(ShortLink $shortLink): bool
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        if (!$settings->isSiteEnabled($shortLink->siteId)) {
            return false;
        }

        return !in_array($shortLink->getStatus(), [
            ShortLink::STATUS_DISABLED,
            ShortLink::STATUS_PENDING,
        ], true);
    }

    private static function resolveDestinationUrl(ShortLink $shortLink): ?string
    {
        $destinationUrl = $shortLink->destinationUrl;

        if (($destinationUrl === null || $destinationUrl === '') && $shortLink->elementId) {
            $destinationUrl = $shortLink->getLinkedElement()?->getUrl();
        }

        return $destinationUrl;
    }

    private static function sanitizeNullableRedirectUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return UrlSafetyHelper::sanitizeRedirectUrl($url);
    }

    private static function shouldPassQueryParams(ShortLink $shortLink): bool
    {
        return (bool)($shortLink->passQueryParams ?? ShortLinkManager::$plugin->getSettings()->passQueryParams);
    }

    private static function mergeQueryString(string $destinationUrl, string $queryString): string
    {
        $queryString = ltrim(trim($queryString), '?');
        if ($queryString === '') {
            return $destinationUrl;
        }

        parse_str($queryString, $incomingParams);
        foreach (['src', 'debug', 'p'] as $excludedParam) {
            unset($incomingParams[$excludedParam]);
        }

        if ($incomingParams === []) {
            return $destinationUrl;
        }

        $parsedUrl = parse_url($destinationUrl);
        if ($parsedUrl === false) {
            return $destinationUrl;
        }

        $existingParams = [];
        if (!empty($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $existingParams);
        }

        $mergedQuery = http_build_query(array_merge($existingParams, $incomingParams));
        $scheme = !empty($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
        $auth = self::buildUrlAuth($parsedUrl);
        $host = $parsedUrl['host'] ?? '';
        $port = !empty($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $path = $parsedUrl['path'] ?? '';
        $fragment = !empty($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

        if ($scheme === '' && str_starts_with($destinationUrl, '/')) {
            return $path . '?' . $mergedQuery . $fragment;
        }

        return $scheme . $auth . $host . $port . $path . '?' . $mergedQuery . $fragment;
    }

    /**
     * @param array<string, mixed> $parsedUrl
     */
    private static function buildUrlAuth(array $parsedUrl): string
    {
        if (empty($parsedUrl['user'])) {
            return '';
        }

        $auth = $parsedUrl['user'];
        if (!empty($parsedUrl['pass'])) {
            $auth .= ':' . $parsedUrl['pass'];
        }

        return $auth . '@';
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(ShortLink $shortLink, ?string $resolvedDestinationUrl = null): array
    {
        return [
            'id' => $shortLink->id,
            'siteId' => $shortLink->siteId,
            'title' => $shortLink->title,
            'code' => $shortLink->code,
            'slug' => $shortLink->slug,
            'linkType' => $shortLink->linkType,
            'shortLinkType' => $shortLink->shortLinkType,
            'destinationUrl' => $shortLink->destinationUrl,
            'resolvedDestinationUrl' => $resolvedDestinationUrl,
            'expiredRedirectUrl' => $shortLink->expiredRedirectUrl,
            'expiredMessage' => $shortLink->expiredMessage,
            'url' => $shortLink->getUrl(),
            'qrCodeUrl' => $shortLink->getQrCodeUrl(),
            'status' => $shortLink->getStatus(),
            'httpCode' => $shortLink->httpCode,
            'enabled' => $shortLink->enabled,
            'trackAnalytics' => $shortLink->trackAnalytics,
            'passQueryParams' => $shortLink->passQueryParams,
            'directRedirect' => $shortLink->directRedirect,
            'hits' => $shortLink->hits,
            'dateExpired' => $shortLink->dateExpired?->format('Y-m-d H:i:s'),
        ];
    }
}
