<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\shortlinkmanager\integrations\seomatic\SeoShortLink;
use lindemannrock\shortlinkmanager\integrations\seomatic\ShortLinkSeoSource;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.22.0
 */
#[CoversClass(SeoShortLink::class)]
#[CoversClass(ShortLinkSeoSource::class)]
class SeomaticSeoElementTest extends TestCase
{
    public function testShortLinkSeoElementMapsSyntheticContentSource(): void
    {
        $source = SeoShortLink::sourceModelFromHandle(SeoShortLink::SOURCE_HANDLE);

        $this->assertInstanceOf(ShortLinkSeoSource::class, $source);
        $this->assertSame('shortlink', SeoShortLink::getMetaBundleType());
        $this->assertSame(['lindemannrock\shortlinkmanager\elements\ShortLink'], SeoShortLink::getElementClasses());
        $this->assertSame('shortLink', SeoShortLink::getElementRefHandle());
        $this->assertSame('shortlink-manager', SeoShortLink::getRequiredPluginHandle());
        $this->assertSame(SeoShortLink::SOURCE_ID, $source->id);
        $this->assertSame(SeoShortLink::SOURCE_HANDLE, $source->handle);
        $this->assertSame(SeoShortLink::SOURCE_TYPE, $source->type);
        $this->assertNotEmpty($source->getSiteSettings());
    }

    public function testMetaBundleConfigUsesShortLinkSourceAndDisablesSitemaps(): void
    {
        $source = SeoShortLink::sourceModelFromId(SeoShortLink::SOURCE_ID);
        $this->assertInstanceOf(ShortLinkSeoSource::class, $source);

        $config = SeoShortLink::metaBundleConfig($source);

        $this->assertSame('shortlink', $config['sourceBundleType']);
        $this->assertSame(SeoShortLink::SOURCE_ID, $config['sourceId']);
        $this->assertSame(SeoShortLink::SOURCE_HANDLE, $config['sourceHandle']);
        $this->assertSame(SeoShortLink::SOURCE_TYPE, $config['sourceType']);
        $this->assertSame('{{ seomatic.helper.extractTextFromField(shortLink.title) }}', $config['metaGlobalVars']['seoTitle']);
        $this->assertSame('{{ shortLink.url }}', $config['metaGlobalVars']['canonicalUrl']);
        $this->assertSame('noindex,nofollow', $config['metaGlobalVars']['robots']);
        $this->assertSame('fromField', $config['metaBundleSettings']['seoTitleSource']);
        $this->assertSame('title', $config['metaBundleSettings']['seoTitleField']);
        $this->assertFalse($config['metaSitemapVars']['sitemapUrls']);
        $this->assertFalse($config['metaSitemapVars']['sitemapAssets']);
    }

    public function testShortLinkElementMapsBackToSyntheticSource(): void
    {
        $shortLink = $this->seedShortLink();

        $this->assertSame(SeoShortLink::SOURCE_ID, SeoShortLink::sourceIdFromElement($shortLink));
        $this->assertNull(SeoShortLink::typeIdFromElement($shortLink));
        $this->assertSame(SeoShortLink::SOURCE_HANDLE, SeoShortLink::sourceHandleFromElement($shortLink));
        $this->assertStringEndsWith('/' . $shortLink->slug, $shortLink->getUrl());
        $this->assertStringContainsString((string) $shortLink->slug, (string) SeoShortLink::previewUri(SeoShortLink::SOURCE_HANDLE, $shortLink->siteId));
    }

    public function testSitemapElementsQueryReturnsShortLinksForContentSeoCount(): void
    {
        $shortLink = $this->seedShortLink();
        $source = SeoShortLink::sourceModelFromId(SeoShortLink::SOURCE_ID);
        $this->assertInstanceOf(ShortLinkSeoSource::class, $source);

        $config = SeoShortLink::metaBundleConfig($source);
        $config['sourceSiteId'] = $shortLink->siteId;
        $metaBundle = \nystudio107\seomatic\models\MetaBundle::create($config);

        $this->assertNotNull($metaBundle);
        $this->assertFalse($metaBundle->metaSitemapVars->sitemapUrls);
        $query = SeoShortLink::sitemapElementsQuery($metaBundle);
        $this->assertGreaterThanOrEqual(1, $query->count());

        $sitemapElement = $query->id($shortLink->id)->one();
        $this->assertNotNull($sitemapElement);
        $this->assertSame($shortLink->getUrl(), $sitemapElement->uri);
    }
}
