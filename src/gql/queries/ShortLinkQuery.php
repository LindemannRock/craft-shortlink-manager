<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\gql\queries;

use craft\gql\base\Query;
use GraphQL\Type\Definition\Type;
use lindemannrock\base\helpers\GqlHelper;
use lindemannrock\shortlinkmanager\gql\resolvers\ShortLinkResolver;
use lindemannrock\shortlinkmanager\gql\types\ShortLinkType;

/**
 * GraphQL queries for ShortLink Manager.
 *
 * @since 5.21.0
 */
class ShortLinkQuery extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canQuery('shortlinkManager.all')) {
            return [];
        }

        return [
            'shortlinkManagerResolveShortlink' => [
                'type' => ShortLinkType::getType(),
                'args' => [
                    'code' => [
                        'name' => 'code',
                        'type' => Type::nonNull(Type::string()),
                        'description' => 'The shortlink code or slug to resolve.',
                    ],
                    'site' => [
                        'name' => 'site',
                        'type' => Type::string(),
                        'description' => 'The site handle to resolve against.',
                    ],
                    'siteId' => [
                        'name' => 'siteId',
                        'type' => Type::int(),
                        'description' => 'The site ID to resolve against.',
                    ],
                    'queryString' => [
                        'name' => 'queryString',
                        'type' => Type::string(),
                        'description' => 'Optional query string from the original frontend URL.',
                    ],
                ],
                'resolve' => ShortLinkResolver::class . '::resolve',
                'description' => 'Resolves a shortlink and records hits/analytics like a real shortlink request.',
            ],
            'shortlinkManagerShortlinks' => [
                'type' => Type::listOf(ShortLinkType::getType()),
                'args' => [
                    'site' => [
                        'name' => 'site',
                        'type' => Type::string(),
                        'description' => 'The site handle to list shortlinks for.',
                    ],
                    'siteId' => [
                        'name' => 'siteId',
                        'type' => Type::int(),
                        'description' => 'The site ID to list shortlinks for.',
                    ],
                    'limit' => [
                        'name' => 'limit',
                        'type' => Type::int(),
                        'description' => 'The maximum number of shortlinks to return.',
                    ],
                ],
                'resolve' => ShortLinkResolver::class . '::resolveAll',
                'description' => 'Lists enabled shortlinks for a site. This query is read-only.',
            ],
        ];
    }
}
