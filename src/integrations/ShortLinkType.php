<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\integrations;

use Craft;
use craft\base\ElementInterface;
use craft\fields\Link;
use craft\fields\linktypes\BaseElementLinkType;
use craft\helpers\Cp;
use craft\helpers\Html;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Short Link Type for Link Field
 *
 * @since 5.2.0
 */
class ShortLinkType extends BaseElementLinkType
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        return $settings->getDisplayName();
    }

    /**
     * @inheritdoc
     */
    public static function elementType(): string
    {
        return ShortLink::class;
    }

    /**
     * @inheritdoc
     */
    public function inputHtml(Link $field, ?string $value, string $containerId): string
    {
        $id = sprintf('shortlink%s', mt_rand());

        // Get the site ID based on the current context
        $siteId = null;

        // Try to get site from POST data (when saving)
        if (Craft::$app->getRequest()->getIsPost()) {
            $siteId = Craft::$app->getRequest()->getBodyParam('siteId');
        }

        // Try to get site from query param (when editing)
        if (!$siteId) {
            $siteHandle = Craft::$app->getRequest()->getQueryParam('site');
            if ($siteHandle) {
                $site = Craft::$app->sites->getSiteByHandle($siteHandle);
                if ($site) {
                    $siteId = $site->id;
                }
            }
        }

        // Fall back to current site
        if (!$siteId) {
            $siteId = Craft::$app->sites->currentSite->id;
        }

        // Check if shortlinks are enabled for this site
        $settings = ShortLinkManager::$plugin->getSettings();
        $enabledSites = $settings->enabledSites ?? [];
        $siteEnabled = empty($enabledSites) || in_array($siteId, $enabledSites);

        // Parse the value to get the element
        $shortLink = null;
        if ($value) {
            $matches = [];
            if (preg_match('/^{shortLink:(\d+)(@(\d+))?:url}$/', $value, $matches)) {
                $elementId = $matches[1];
                // Always use current site context (ignore stored siteId)
                $shortLink = ShortLink::find()
                    ->id($elementId)
                    ->siteId($siteId)
                    ->status(null)
                    ->one();
            }
        }

        // Get site for the field
        $currentSite = Craft::$app->sites->getSiteById($siteId);

        // If site is not enabled, show warning
        if (!$siteEnabled) {
            $pluginName = ShortLinkManager::$plugin->getSettings()->getFullName();
            return Html::tag('div',
                Html::tag('p', Craft::t('shortlink-manager', '{pluginName} is not enabled for site "{site}". Enable it in plugin settings to use {pluginNameLower} here.', [
                    'pluginName' => $pluginName,
                    'pluginNameLower' => ShortLinkManager::$plugin->getSettings()->getPluralLowerDisplayName(),
                    'site' => $currentSite->name,
                ]), ['class' => 'warning']),
                ['class' => 'field']
            );
        }

        return Cp::elementSelectFieldHtml([
            'id' => $id,
            'name' => 'value',
            'elements' => $shortLink ? [$shortLink] : [],
            'elementType' => ShortLink::class,
            'sources' => $this->sources,
            'criteria' => [
                'status' => 'enabled',
                'siteId' => $currentSite->id,
            ],
            'single' => true,
            'showSiteMenu' => false,
            'modalSettings' => [
                'defaultSiteId' => $currentSite->id,
                'criteria' => [
                    'siteId' => $currentSite->id,
                ],
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function renderValue(string $value): string
    {
        $element = $this->element($value);
        if (!$element instanceof ShortLink) {
            return '';
        }

        // Get destination URL for current site
        try {
            return $element->getUrl();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * @inheritdoc
     */
    public function linkLabel(string $value): string
    {
        $element = $this->element($value);
        return $element instanceof ShortLink ? $element->title : '';
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue(ElementInterface|string|int $value): string
    {
        if ($value instanceof ShortLink) {
            return sprintf('{shortLink:%s@%s:url}', $value->id, $value->siteId);
        }

        if (is_numeric($value)) {
            // If we get a numeric ID, we need to determine the correct site
            $siteId = $this->detectCurrentSiteId();
            return sprintf('{shortLink:%s@%s:url}', $value, $siteId);
        }

        return parent::normalizeValue($value);
    }

    /**
     * @inheritdoc
     */
    public function value(mixed $element): ?string
    {
        if ($element instanceof ShortLink) {
            return sprintf('{shortLink:%s@%s:url}', $element->id, $element->siteId);
        }
        return null;
    }

    /**
     * Detect the current site ID from the request context
     */
    private function detectCurrentSiteId(): int
    {
        // On frontend, always use the current site
        if (Craft::$app->getRequest()->getIsSiteRequest()) {
            return Craft::$app->getSites()->getCurrentSite()->id;
        }

        // In CP, try POST data first (when saving)
        if (Craft::$app->getRequest()->getIsPost()) {
            $siteId = Craft::$app->getRequest()->getBodyParam('siteId');
            if ($siteId) {
                return (int)$siteId;
            }
        }

        // Try query param (CP editing)
        $siteHandle = Craft::$app->getRequest()->getQueryParam('site');
        if ($siteHandle && $site = Craft::$app->sites->getSiteByHandle($siteHandle)) {
            return $site->id;
        }

        // Default to current site
        return Craft::$app->getSites()->getCurrentSite()->id;
    }

    /**
     * @inheritdoc
     */
    public function validateValue(string $value, ?string &$error = null): bool
    {
        // Parse the value to get the element ID
        $matches = [];
        if (!preg_match('/^{shortLink:(\d+)(@(\d+))?:url}$/', $value, $matches)) {
            $error = Craft::t('shortlink-manager', 'Invalid {pluginName} format.', [
                'pluginName' => ShortLinkManager::$plugin->getSettings()->getLowerDisplayName(),
            ]);
            return false;
        }

        $elementId = $matches[1];

        // Use current site context for validation
        $currentSiteId = $this->detectCurrentSiteId();

        $shortLink = ShortLink::find()
            ->id($elementId)
            ->siteId($currentSiteId)
            ->status(null)
            ->one();

        if (!$shortLink) {
            $error = Craft::t('shortlink-manager', '{pluginName} not found.', [
                'pluginName' => ShortLinkManager::$plugin->getSettings()->getDisplayName(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function isValueEmpty(string $value): bool
    {
        return !$this->element($value);
    }

    /**
     * @inheritdoc
     */
    public function element(?string $value): ?ElementInterface
    {
        if (!$value) {
            return null;
        }

        $matches = [];
        if (!preg_match('/^{shortLink:(\d+)(@(\d+))?:url}$/', $value, $matches)) {
            return null;
        }

        $elementId = $matches[1];

        // Always use CURRENT site context (not the stored siteId)
        // This ensures the shortlink adapts to the current site like Entry fields do
        $currentSiteId = $this->detectCurrentSiteId();

        // Check if shortlinks are enabled for the current site
        $settings = ShortLinkManager::$plugin->getSettings();
        $enabledSites = $settings->enabledSites ?? [];
        $siteEnabled = empty($enabledSites) || in_array($currentSiteId, $enabledSites);

        // If site is not enabled, return null (field will be empty)
        if (!$siteEnabled) {
            return null;
        }

        $shortLink = ShortLink::find()
            ->id($elementId)
            ->siteId($currentSiteId)
            ->status(null)
            ->one();

        // If not found for current site, try to find in any enabled site (fallback)
        if (!$shortLink) {
            $shortLink = ShortLink::find()
                ->id($elementId)
                ->siteId('*')
                ->status(null)
                ->one();
        }

        return $shortLink;
    }
}
