<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\integrations\seomatic;

use Craft;
use craft\base\ElementInterface;
use craft\base\Model;
use craft\elements\db\ElementQueryInterface;
use craft\events\DefineHtmlEvent;
use craft\models\FieldLayout;
use craft\models\Site;
use DateTime;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\integrations\SeomaticIntegration;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use nystudio107\seomatic\assetbundles\seomatic\SeomaticAsset;
use nystudio107\seomatic\base\SeoElementInterface;
use nystudio107\seomatic\helpers\ArrayHelper;
use nystudio107\seomatic\helpers\Config as ConfigHelper;
use nystudio107\seomatic\helpers\PluginTemplate;
use nystudio107\seomatic\models\MetaBundle;
use nystudio107\seomatic\Seomatic;
use yii\base\Event;

/**
 * SEOmatic adapter for ShortLink elements.
 *
 * ShortLinks are route-backed utility elements, so they intentionally do not
 * expose Craft element URIs. This adapter gives SEOmatic a single synthetic
 * content source and the runtime integration explicitly sets the matched
 * element before SEOmatic loads its meta containers.
 *
 * @since 5.22.0
 */
class SeoShortLink implements SeoElementInterface
{
    public const META_BUNDLE_TYPE = 'shortlink';
    public const SOURCE_ID = 1;
    public const SOURCE_HANDLE = 'shortlinks';
    public const SOURCE_TYPE = 'shortlinks';
    public const CONFIG_FILE_PATH = 'entrymeta/Bundle';

    public static function getMetaBundleType(): string
    {
        return self::META_BUNDLE_TYPE;
    }

    public static function getElementClasses(): array
    {
        return [ShortLink::class];
    }

    public static function getElementRefHandle(): string
    {
        return ShortLink::refHandle() ?? 'shortLink';
    }

    public static function getRequiredPluginHandle(): ?string
    {
        return 'shortlink-manager';
    }

    public static function installEventHandlers(): void
    {
        Event::on(
            ShortLink::class,
            ShortLink::EVENT_DEFINE_SIDEBAR_HTML,
            static function(DefineHtmlEvent $event): void {
                /** @var ShortLink|null $shortLink */
                $shortLink = $event->sender ?? null;

                if (!$shortLink instanceof ShortLink || $shortLink->id === null) {
                    return;
                }

                $seomatic = ShortLinkManager::$plugin->integration->getIntegration('seomatic');

                if (!$seomatic instanceof SeomaticIntegration || !$seomatic->isAvailable() || !$seomatic->isEnabled()) {
                    return;
                }

                Seomatic::$view->registerAssetBundle(SeomaticAsset::class);
                Seomatic::setMatchedElement($shortLink);
                Seomatic::$plugin->metaContainers->previewMetaContainers(
                    self::relativeUri($shortLink) ?? '',
                    $shortLink->siteId,
                    true,
                    true,
                    $shortLink
                );

                if (Seomatic::$settings->displayPreviewSidebar && Seomatic::$matchedElement) {
                    $event->html .= PluginTemplate::renderPluginTemplate('_sidebars/entry-preview.twig');
                }
            }
        );
    }

    public static function sitemapElementsQuery(MetaBundle $metaBundle): ElementQueryInterface
    {
        return ShortLink::find()
            ->siteId($metaBundle->sourceSiteId)
            ->withSyntheticUris()
            ->limit($metaBundle->metaSitemapVars->sitemapLimit);
    }

    public static function sitemapAltElement(
        MetaBundle $metaBundle,
        int $elementId,
        int $siteId,
    ): ?ElementInterface {
        return ShortLink::find()
            ->id($elementId)
            ->siteId($siteId)
            ->limit(1)
            ->one();
    }

    public static function previewUri(string $sourceHandle, $siteId, $typeId = null): ?string
    {
        if ($sourceHandle !== self::SOURCE_HANDLE) {
            return null;
        }

        $element = ShortLink::find()
            ->siteId($siteId)
            ->limit(1)
            ->one();

        return $element instanceof ShortLink ? self::relativeUri($element) : null;
    }

    /**
     * @return FieldLayout[]
     */
    public static function fieldLayouts(string $sourceHandle, $typeId = null): array
    {
        if ($sourceHandle !== self::SOURCE_HANDLE) {
            return [];
        }

        $layout = Craft::$app->getFields()->getLayoutByType(ShortLink::class);

        return $layout instanceof FieldLayout ? [$layout] : [];
    }

    public static function typeMenuFromHandle(string $sourceHandle): array
    {
        return [];
    }

    public static function sourceModelFromId(int $sourceId): ?ShortLinkSeoSource
    {
        return $sourceId === self::SOURCE_ID ? self::sourceModel() : null;
    }

    public static function sourceModelFromHandle(string $sourceHandle): ?ShortLinkSeoSource
    {
        return $sourceHandle === self::SOURCE_HANDLE ? self::sourceModel() : null;
    }

    public static function mostRecentElement(Model $sourceModel, int $sourceSiteId): ?ElementInterface
    {
        return ShortLink::find()
            ->siteId($sourceSiteId)
            ->limit(1)
            ->orderBy(['elements.dateUpdated' => SORT_DESC])
            ->one();
    }

    public static function configFilePath(): string
    {
        return self::CONFIG_FILE_PATH;
    }

    public static function metaBundleConfig(Model $sourceModel): array
    {
        /** @var ShortLinkSeoSource $sourceModel */
        return ArrayHelper::merge(
            ConfigHelper::getConfigFromFile(self::configFilePath()),
            [
                'sourceBundleType' => self::getMetaBundleType(),
                'sourceId' => $sourceModel->id,
                'sourceName' => $sourceModel->getName(),
                'sourceHandle' => $sourceModel->handle,
                'sourceType' => $sourceModel->type,
                'sourceDateUpdated' => new DateTime(),
                'metaGlobalVars' => [
                    'seoTitle' => '{{ seomatic.helper.extractTextFromField(shortLink.title) }}',
                    'canonicalUrl' => '{{ shortLink.url }}',
                    'robots' => 'noindex,nofollow',
                ],
                'metaBundleSettings' => [
                    'seoTitleSource' => 'fromField',
                    'seoTitleField' => 'title',
                ],
                'metaSitemapVars' => [
                    'sitemapUrls' => false,
                    'sitemapAssets' => false,
                    'sitemapFiles' => false,
                    'sitemapAltLinks' => false,
                ],
            ]
        );
    }

    public static function sourceIdFromElement(ElementInterface $element): ?int
    {
        return $element instanceof ShortLink ? self::SOURCE_ID : null;
    }

    public static function typeIdFromElement(ElementInterface $element): ?int
    {
        return null;
    }

    public static function sourceHandleFromElement(ElementInterface $element): ?string
    {
        return $element instanceof ShortLink ? self::SOURCE_HANDLE : null;
    }

    public static function createContentMetaBundle(Model $sourceModel): void
    {
        /** @var Site $site */
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            Seomatic::$plugin->metaBundles->createMetaBundleFromSeoElement(
                self::class,
                $sourceModel,
                $site->id,
                null,
                true
            );
        }
    }

    public static function createAllContentMetaBundles(): void
    {
        self::createContentMetaBundle(self::sourceModel());
    }

    private static function sourceModel(): ShortLinkSeoSource
    {
        return new ShortLinkSeoSource();
    }

    private static function relativeUri(ShortLink $element): ?string
    {
        $path = parse_url($element->getUrl(), PHP_URL_PATH);

        if (!is_string($path)) {
            return null;
        }

        return trim($path, '/');
    }
}
