<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\integrations\ShortLinkType;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins the Craft native Link field integration for ShortLink elements.
 *
 * @since 5.21.0
 */
final class NativeLinkFieldIntegrationTest extends TestCase
{
    public function testNativeLinkTypeFormatsAndResolvesShortLinkValue(): void
    {
        $shortLink = $this->seedShortLink([
            'code' => 'sl-test-native-link',
            'slug' => 'sl-test-native-link',
            'destinationUrl' => 'https://example.com/native-link',
        ]);
        $type = new ShortLinkType();

        $expectedValue = sprintf('{shortLink:%s@%s:url}', $shortLink->id, $shortLink->siteId);

        self::assertSame($expectedValue, $type->value($shortLink));
        self::assertSame($expectedValue, $type->normalizeValue($shortLink));

        $resolved = $type->element($expectedValue);
        self::assertInstanceOf(ShortLink::class, $resolved);
        self::assertSame($shortLink->id, $resolved->id);

        self::assertSame($resolved->getUrl(), $type->renderValue($expectedValue));
        self::assertSame($resolved->title, $type->linkLabel($expectedValue));
        self::assertFalse($type->isValueEmpty($expectedValue));
    }

    public function testNativeLinkTypeValidationRejectsMalformedValues(): void
    {
        $type = new ShortLinkType();
        $error = null;

        self::assertFalse($type->validateValue('not-a-shortlink-token', $error));
        self::assertIsString($error);
        self::assertStringContainsString('Invalid', $error);
        self::assertTrue($type->isValueEmpty('not-a-shortlink-token'));
    }

    public function testNativeLinkTypeGraphqlFieldIdMatchesShortLinkRefHandle(): void
    {
        self::assertSame('shortLink', ShortLink::refHandle());
        self::assertSame(ShortLink::refHandle(), ShortLinkType::id());
        self::assertSame('ShortLinkManagerShortLink', ShortLinkType::elementGqlType()->name);
    }
}
