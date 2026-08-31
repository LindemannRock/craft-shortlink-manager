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
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins the core element-free shortlink type matrix: generated-code links,
 * vanity links, manual links, and field-managed auto links.
 *
 * @since 5.20.0
 */
final class ShortLinkTypeTest extends TestCase
{
    public function testCodeLinkTypeGeneratesCodeAndSlug(): void
    {
        $link = $this->withSettings(['codeLength' => 10], fn(): ?ShortLink => $this->shortLinks->createShortLink([
            'linkType' => 'code',
            'shortLinkType' => 'manual',
            'destinationUrl' => 'https://example.com/generated',
        ]));

        self::assertInstanceOf(ShortLink::class, $link);
        $this->trackShortLinkForCleanup($link);
        self::assertSame('code', $link->linkType);
        self::assertSame('manual', $link->shortLinkType);
        self::assertSame(10, strlen((string) $link->code));
        self::assertSame($link->code, $link->slug);
    }

    public function testVanityLinkTypeNormalizesCustomCode(): void
    {
        $link = $this->shortLinks->createShortLink([
            'linkType' => 'vanity',
            'shortLinkType' => 'manual',
            'code' => 'SL Test Custom Code',
            'destinationUrl' => 'https://example.com/custom',
        ]);

        self::assertInstanceOf(ShortLink::class, $link);
        $this->trackShortLinkForCleanup($link);
        self::assertSame('vanity', $link->linkType);
        self::assertSame('manual', $link->shortLinkType);
        self::assertSame('sl-test-custom-code', $link->code);
        self::assertSame('sl-test-custom-code', $link->slug);
    }

    public function testVanityLinkWithoutCodeIsRejected(): void
    {
        $link = $this->shortLinks->createShortLink([
            'linkType' => 'vanity',
            'shortLinkType' => 'manual',
            'destinationUrl' => 'https://example.com/custom',
        ]);

        self::assertNull($link);
    }

    public function testAutoShortLinkTypeCanBeSavedWithElementReference(): void
    {
        $link = $this->seedShortLink([
            'code' => 'sl-test-auto-field',
            'slug' => 'sl-test-auto-field',
            'shortLinkType' => 'auto',
            'destinationUrl' => 'https://example.com/entry',
        ]);

        // `elementId` is FK-constrained to `elements.id`; use this saved
        // element's own id to pin persistence without depending on entry fixtures.
        $link->elementId = (int) $link->id;
        $link->elementType = \craft\elements\Entry::class;

        self::assertTrue(
            $this->shortLinks->saveShortLink($link),
            'Auto shortlink should persist its field-managed marker fields: ' . json_encode($link->getErrors()),
        );

        $row = $this->fetchRow('{{%shortlinkmanager_content}}', [
            'shortLinkId' => $link->id,
            'siteId' => $link->siteId,
        ]);
        self::assertNotNull($row);
        self::assertSame(\craft\elements\Entry::class, $row['elementType']);
        self::assertSame((int) $link->id, (int) $row['elementId']);
    }

    public function testCommerceProductLinkedShortLinkResolvesDestinationWhenAvailable(): void
    {
        $productClass = 'craft\\commerce\\elements\\Product';

        if (!Craft::$app->getPlugins()->isPluginInstalled('commerce') || !class_exists($productClass)) {
            self::markTestSkipped('Craft Commerce Product elements are not available.');
        }

        /** @var class-string<\craft\base\Element> $productClass */
        $product = $productClass::find()
            ->siteId(Craft::$app->getSites()->getPrimarySite()->id)
            ->status(null)
            ->one();

        if (!$product instanceof \craft\base\ElementInterface) {
            self::markTestSkipped('No Commerce product fixture is available.');
        }

        $productUrl = $product->getUrl();
        if ($productUrl === null || $productUrl === '') {
            self::markTestSkipped('The Commerce product fixture has no URL.');
        }

        $link = $this->seedShortLink([
            'code' => 'sl-test-commerce-product',
            'slug' => 'sl-test-commerce-product',
            'destinationUrl' => $productUrl,
            'siteId' => $product->siteId,
        ]);
        $link->elementId = (int) $product->id;
        $link->elementType = $productClass;

        self::assertTrue(
            $this->shortLinks->saveShortLink($link),
            'Product-linked shortlink should save: ' . json_encode($link->getErrors()),
        );

        self::assertSame($productUrl, $link->getLinkedElement()?->getUrl());
    }
}
