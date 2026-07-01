<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\widgets;

use Craft;
use craft\base\Widget;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * ShortLink Manager Analytics Summary Widget
 *
 * @since 5.0.0
 */
class AnalyticsSummaryWidget extends Widget
{
    use SiteFilterTrait;

    /**
     * @var string Date range for analytics
     */
    public string $dateRange = 'last7days';

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['dateRange'], 'in', 'range' => array_keys(DateRangeHelper::getOptions('assoc'))];
        $rules[] = [['siteId'], 'in', 'range' => array_column($this->siteOptions(), 'value')];
        $rules[] = [['dateRange'], 'default', 'value' => 'last7days'];
        $rules[] = [['siteId'], 'default', 'value' => 'all'];
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $pluginName = ShortLinkManager::$plugin->getSettings()->getFullName();
        return Craft::t('shortlink-manager', '{pluginName} - Analytics', ['pluginName' => $pluginName]);
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return parent::isSelectable() &&
            Craft::$app->getUser()->checkPermission('shortLinkManager:viewAnalytics');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return '@lindemannrock/shortlinkmanager/icon-mask.svg';
    }

    /**
     * @inheritdoc
     */
    public static function maxColspan(): ?int
    {
        return 2;
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): ?string
    {
        $pluginName = ShortLinkManager::$plugin->getSettings()->getFullName();
        return Craft::t('shortlink-manager', '{pluginName} - Analytics', ['pluginName' => $pluginName]);
    }

    /**
     * @inheritdoc
     */
    public function getSubtitle(): ?string
    {
        $labels = DateRangeHelper::getOptions('assoc');

        return $labels[$this->dateRange] ?? $labels['last7days'];
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('shortlink-manager/widgets/analytics-summary/settings', [
            'widget' => $this,
            'siteOptions' => $this->siteOptions(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        // Check permission
        if (!Craft::$app->getUser()->checkPermission('shortLinkManager:viewAnalytics')) {
            return '<p class="light">' . Craft::t('shortlink-manager', 'You don\'t have permission to view analytics.') . '</p>';
        }

        // Check if analytics are enabled
        if (!ShortLinkManager::$plugin->getSettings()->enableAnalytics) {
            return '<p class="light">' . Craft::t('shortlink-manager', 'Analytics are disabled in plugin settings.') . '</p>';
        }

        $analyticsData = ShortLinkManager::$plugin->analytics->getAnalyticsSummary($this->dateRange, null, $this->effectiveSiteId());

        return Craft::$app->getView()->renderTemplate('shortlink-manager/widgets/analytics-summary/body', [
            'widget' => $this,
            'data' => $analyticsData,
        ]);
    }
}
