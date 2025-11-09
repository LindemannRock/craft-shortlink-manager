<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\jobs;

use Craft;
use craft\queue\BaseJob;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Cleanup Analytics Job
 */
class CleanupAnalyticsJob extends BaseJob
{
    use LoggingTrait;

    /**
     * @var bool Whether to reschedule after completion
     */
    public bool $reschedule = false;

    /**
     * @var string|null Next run time display string
     */
    public ?string $nextRunTime = null;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('shortlink-manager');

        // Calculate and set next run time if not already set
        if ($this->reschedule && !$this->nextRunTime) {
            $delay = $this->calculateNextRunDelay();
            if ($delay > 0) {
                // Short format: "Nov 8, 12:00am"
                $this->nextRunTime = date('M j, g:ia', time() + $delay);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        // Only run if retention is enabled
        if ($settings->analyticsRetention > 0) {
            $deleted = ShortLinkManager::$plugin->analytics->cleanupOldAnalytics();
            $this->logInfo('Cleaned up old analytics records', ['deleted' => $deleted]);
        }

        // Reschedule if needed
        if ($this->reschedule) {
            $this->scheduleNextCleanup();
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $description = Craft::t('shortlink-manager', '{pluginName}: Cleaning up old analytics', [
            'pluginName' => $settings->pluginName,
        ]);

        if ($this->nextRunTime) {
            $description .= " ({$this->nextRunTime})";
        }

        return $description;
    }

    /**
     * Schedule the next cleanup (runs every 24 hours)
     */
    private function scheduleNextCleanup(): void
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        // Only reschedule if analytics is enabled and retention is set
        if (!$settings->enableAnalytics || $settings->analyticsRetention <= 0) {
            return;
        }

        $delay = $this->calculateNextRunDelay();

        if ($delay > 0) {
            // Calculate next run time for display
            $nextRunTime = date('M j, g:ia', time() + $delay);

            $job = new self([
                'reschedule' => true,
                'nextRunTime' => $nextRunTime,
            ]);

            Craft::$app->getQueue()->delay($delay)->push($job);
        }
    }

    /**
     * Calculate the delay in seconds for the next cleanup (24 hours)
     */
    private function calculateNextRunDelay(): int
    {
        return 86400; // 24 hours
    }
}
