<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\jobs;

use Craft;
use craft\queue\BaseJob;
use lindemannrock\base\traits\QueueTtrTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\queue\RetryableJobInterface;

/**
 * Cleanup Analytics Job
 *
 * @since 5.0.0
 */
class CleanupAnalyticsJob extends BaseJob implements RetryableJobInterface
{
    use QueueTtrTrait;
    use LoggingTrait;

    /**
     * @var bool Whether to reschedule after completion
     */
    public bool $reschedule = false;

    /**
     * @var string Recurring chain owner token
     * @since 5.28.4
     */
    public string $recurringOwner = '';

    /**
     * @var string|null Next run time display string
     */
    public ?string $nextRunTime = null;

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);

        if ($this->reschedule && !$this->nextRunTime) {
            $settings = ShortLinkManager::$plugin->getSettings();
            $scheduler = ShortLinkManager::$plugin->analyticsCleanupScheduler;
            $nextRun = $scheduler->nextRun();
            if ($nextRun !== null) {
                $this->nextRunTime = $scheduler->formatNextRun($nextRun, $settings);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        $scheduledCleanupEnabled = !$this->reschedule
            || ShortLinkManager::$plugin->analyticsCleanupScheduler->isEnabled($settings);

        // Manual cleanup remains available independently of automatic scheduling.
        if ($scheduledCleanupEnabled && $settings->analyticsRetention > 0) {
            $deleted = ShortLinkManager::$plugin->analytics->cleanupOldAnalytics();
            $this->logInfo('Cleaned up old analytics records', ['deleted' => $deleted]);
        }

        // Reschedule if needed
        if ($this->reschedule && $scheduledCleanupEnabled) {
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
            'pluginName' => $settings->getDisplayName(),
        ]);

        if ($this->nextRunTime) {
            $description .= " ({$this->nextRunTime})";
        }

        return $description;
    }

    /**
     * Schedule the next cleanup at the next canonical daily midnight.
     */
    private function scheduleNextCleanup(): void
    {
        $result = ShortLinkManager::$plugin->analyticsCleanupScheduler->ensureScheduled(
            ShortLinkManager::$plugin->getSettings(),
        );

        $this->logDebug('Ensured next analytics cleanup', [
            'status' => $result->status,
            'jobId' => $result->jobId,
        ]);
    }
}
