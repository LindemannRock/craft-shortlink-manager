<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use Craft;
use craft\base\Element;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\fields\ShortLinkField;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins the legacy Craft field lifecycle independently of GraphQL support.
 *
 * @since 5.21.0
 */
final class ShortLinkFieldTest extends TestCase
{
    public function testFieldCreatesVanityShortLinkForElement(): void
    {
        $code = $this->fieldCode('create');
        $field = new ShortLinkField([
            'handle' => 'shortlinkField',
            'linkType' => 'vanity',
            'defaultHttpCode' => 301,
        ]);
        $element = $this->fieldElement($code, 'https://example.com/field-create');

        $field->afterElementSave($element, true);

        $shortLink = $this->shortLinks->getByElement($element, $element->siteId);

        self::assertInstanceOf(ShortLink::class, $shortLink);
        self::assertSame($code, $shortLink->code);
        self::assertSame($code, $shortLink->slug);
        self::assertSame('vanity', $shortLink->linkType);
        self::assertSame('auto', $shortLink->shortLinkType);
        self::assertSame(301, $shortLink->httpCode);
        self::assertSame('https://example.com/field-create', $shortLink->destinationUrl);
    }

    public function testFieldUpdatesDestinationWithoutChangingExistingCode(): void
    {
        $code = $this->fieldCode('original');
        $field = new ShortLinkField([
            'handle' => 'shortlinkField',
            'linkType' => 'vanity',
            'defaultHttpCode' => 302,
        ]);
        $element = $this->fieldElement($code, 'https://example.com/original');

        $field->afterElementSave($element, true);
        $created = $this->shortLinks->getByElement($element, $element->siteId);
        self::assertInstanceOf(ShortLink::class, $created);

        $element->fieldValue = $this->fieldCode('replacement');
        $element->url = 'https://example.com/updated';

        $field->afterElementSave($element, false);

        $updated = ShortLink::find()->id($created->id)->siteId($element->siteId)->status(null)->one();

        self::assertInstanceOf(ShortLink::class, $updated);
        self::assertSame($code, $updated->code);
        self::assertSame($code, $updated->slug);
        self::assertSame('https://example.com/updated', $updated->destinationUrl);
    }

    public function testFieldDeletesAssociatedShortLinkWithElement(): void
    {
        $code = $this->fieldCode('delete');
        $field = new ShortLinkField([
            'handle' => 'shortlinkField',
            'linkType' => 'vanity',
        ]);
        $element = $this->fieldElement($code, 'https://example.com/delete');

        $field->afterElementSave($element, true);
        $created = $this->shortLinks->getByElement($element, $element->siteId);
        self::assertInstanceOf(ShortLink::class, $created);

        $field->afterElementDelete($element);

        self::assertNull(ShortLink::find()->id($created->id)->siteId($element->siteId)->status(null)->one());
    }

    public function testFieldGraphqlResolvesFieldManagedShortLinkObject(): void
    {
        $code = $this->fieldCode('graphql');
        $field = new ShortLinkField([
            'handle' => 'shortLink',
            'linkType' => 'vanity',
        ]);
        $element = $this->fieldElement($code, 'https://example.com/graphql-field');

        $field->afterElementSave($element, true);
        $definition = $field->getContentGqlType();

        self::assertIsArray($definition);
        self::assertSame('shortLink', $definition['name']);
        self::assertIsCallable($definition['resolve']);

        $resolved = $definition['resolve']($element);

        self::assertIsArray($resolved);
        self::assertSame($code, $resolved['code']);
        self::assertSame($code, $resolved['slug']);
        self::assertSame('auto', $resolved['shortLinkType']);
        self::assertSame('https://example.com/graphql-field', $resolved['destinationUrl']);
        self::assertSame('https://example.com/graphql-field', $resolved['resolvedDestinationUrl']);
        self::assertArrayHasKey('url', $resolved);
        self::assertArrayHasKey('qrCodeUrl', $resolved);
    }

    private function fieldElement(string $fieldValue, string $url): ShortLinkFieldTestElement
    {
        $target = $this->seedShortLink([
            'code' => $fieldValue . '-target',
            'slug' => $fieldValue . '-target',
            'destinationUrl' => $url,
        ]);

        $element = new ShortLinkFieldTestElement();
        $element->id = $target->id;
        $element->siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $element->fieldValue = $fieldValue;
        $element->url = $url;

        return $element;
    }

    private function fieldCode(string $suffix): string
    {
        return str_replace('_', '-', $this->nextTestMarker(self::MARKER, 'field-' . $suffix));
    }
}

/**
 * Minimal saved-element stand-in for exercising ShortLinkField lifecycle hooks.
 *
 * @internal
 */
final class ShortLinkFieldTestElement extends Element
{
    public string $fieldValue = '';
    public string $url = '';

    public static function displayName(): string
    {
        return 'ShortLink field test element';
    }

    public function getFieldValue(string $fieldHandle): mixed
    {
        return $this->fieldValue;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }
}
