<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\gql\types;

use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use lindemannrock\base\helpers\GqlHelper;

/**
 * GraphQL object type for ShortLink Manager shortlinks.
 *
 * @since 5.21.0
 */
class ShortLinkType extends ObjectType
{
    /**
     * Return the registered GraphQL type.
     *
     * @return Type
     */
    public static function getType(): Type
    {
        $typeName = self::getName();
        if ($type = GqlEntityRegistry::getEntity($typeName)) {
            return $type;
        }

        return GqlEntityRegistry::createEntity($typeName, new self([
            'name' => $typeName,
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'A ShortLink Manager shortlink.',
        ]));
    }

    /**
     * Return the GraphQL type name.
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'ShortLinkManagerShortLink';
    }

    /**
     * Return field definitions for shortlinks.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getFieldDefinitions(): array
    {
        return [
            'id' => [
                'name' => 'id',
                'type' => Type::int(),
                'description' => 'The shortlink ID.',
            ],
            'site' => [
                'name' => 'site',
                'type' => Type::string(),
                'description' => 'The site handle.',
            ],
            'siteId' => [
                'name' => 'siteId',
                'type' => Type::int(),
                'description' => 'The site ID.',
            ],
            'title' => [
                'name' => 'title',
                'type' => Type::string(),
                'description' => 'The shortlink title.',
            ],
            'code' => [
                'name' => 'code',
                'type' => Type::string(),
                'description' => 'The public short code.',
            ],
            'slug' => [
                'name' => 'slug',
                'type' => Type::string(),
                'description' => 'The normalized shortlink slug.',
            ],
            'linkType' => [
                'name' => 'linkType',
                'type' => Type::string(),
                'description' => 'The shortlink link type.',
            ],
            'shortLinkType' => [
                'name' => 'shortLinkType',
                'type' => Type::string(),
                'description' => 'Whether the shortlink is manual or field-managed.',
            ],
            'url' => [
                'name' => 'url',
                'type' => Type::string(),
                'description' => 'The public shortlink URL.',
            ],
            'qrCodeUrl' => [
                'name' => 'qrCodeUrl',
                'type' => Type::string(),
                'description' => 'The public QR code URL.',
            ],
            'destinationUrl' => [
                'name' => 'destinationUrl',
                'type' => Type::string(),
                'description' => 'The configured destination URL.',
            ],
            'resolvedDestinationUrl' => [
                'name' => 'resolvedDestinationUrl',
                'type' => Type::string(),
                'description' => 'The resolved destination URL for a redirect.',
            ],
            'expiredRedirectUrl' => [
                'name' => 'expiredRedirectUrl',
                'type' => Type::string(),
                'description' => 'The destination used when the link is expired.',
            ],
            'expiredMessage' => [
                'name' => 'expiredMessage',
                'type' => Type::string(),
                'description' => 'The message shown when the link is expired and no redirect is configured.',
            ],
            'status' => [
                'name' => 'status',
                'type' => Type::string(),
                'description' => 'The shortlink status.',
            ],
            'httpCode' => [
                'name' => 'httpCode',
                'type' => Type::int(),
                'description' => 'The HTTP status code.',
            ],
            'enabled' => [
                'name' => 'enabled',
                'type' => Type::boolean(),
                'description' => 'Whether the shortlink is enabled for the site.',
            ],
            'trackAnalytics' => [
                'name' => 'trackAnalytics',
                'type' => Type::boolean(),
                'description' => 'Whether analytics tracking is enabled for the shortlink.',
            ],
            'passQueryParams' => [
                'name' => 'passQueryParams',
                'type' => Type::boolean(),
                'description' => 'Whether the shortlink passes query parameters to its destination.',
            ],
            'directRedirect' => [
                'name' => 'directRedirect',
                'type' => Type::boolean(),
                'description' => 'Whether the shortlink skips the redirect template.',
            ],
            'hits' => [
                'name' => 'hits',
                'type' => Type::int(),
                'description' => 'The number of times this shortlink has been hit.',
            ],
            'dateExpired' => [
                'name' => 'dateExpired',
                'type' => Type::string(),
                'description' => 'The expiry datetime.',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $fieldName = $resolveInfo->fieldName;

        if ($fieldName === 'site') {
            return GqlHelper::siteHandle(isset($source['siteId']) ? (int)$source['siteId'] : null);
        }

        if (is_array($source)) {
            return GqlHelper::nullIfEmptyString($source[$fieldName] ?? null);
        }

        return parent::resolve($source, $arguments, $context, $resolveInfo);
    }
}
