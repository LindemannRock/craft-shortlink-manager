<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\widgets;

use Craft;
use craft\base\Widget;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * ShortLink Manager Analytics Summary Widget
 */
class AnalyticsSummaryWidget extends Widget
{
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
        $rules[] = [['dateRange'], 'string'];
        $rules[] = [['dateRange'], 'default', 'value' => 'last7days'];
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $pluginName = ShortLinkManager::$plugin->getSettings()->pluginName ?? 'ShortLink Manager';
        return Craft::t('shortlink-manager', '{pluginName} - Analytics', ['pluginName' => $pluginName]);
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return '@app/icons/chart-line.svg';
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
        $pluginName = ShortLinkManager::$plugin->getSettings()->pluginName ?? 'ShortLink Manager';
        return Craft::t('shortlink-manager', '{pluginName} - Analytics', ['pluginName' => $pluginName]);
    }

    /**
     * @inheritdoc
     */
    public function getSubtitle(): ?string
    {
        $labels = [
            'today' => Craft::t('shortlink-manager', 'Today'),
            'yesterday' => Craft::t('shortlink-manager', 'Yesterday'),
            'last7days' => Craft::t('shortlink-manager', 'Last 7 days'),
            'last30days' => Craft::t('shortlink-manager', 'Last 30 days'),
            'last90days' => Craft::t('shortlink-manager', 'Last 90 days'),
            'all' => Craft::t('shortlink-manager', 'All time'),
        ];

        return $labels[$this->dateRange] ?? Craft::t('shortlink-manager', 'Last 7 days');
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('shortlink-manager/widgets/analytics-summary/settings', [
            'widget' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        // Check if analytics are enabled
        if (!ShortLinkManager::$plugin->getSettings()->enableAnalytics) {
            return '<p class="light">' . Craft::t('shortlink-manager', 'Analytics are disabled in plugin settings.') . '</p>';
        }

        // Get analytics data
        $analyticsData = ShortLinkManager::$plugin->analytics->getAnalyticsSummary($this->dateRange);

        return Craft::$app->getView()->renderTemplate('shortlink-manager/widgets/analytics-summary/body', [
            'widget' => $this,
            'data' => $analyticsData,
        ]);
    }
}
