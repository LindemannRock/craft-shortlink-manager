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
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\ScheduleHelper;
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
            $nextRun = ScheduleHelper::calculateNext('daily');
            if ($nextRun !== null) {
                $this->nextRunTime = DateFormatHelper::formatCompactDatetimeFromSettings(
                    $nextRun,
                    $settings,
                    false,
                    false,
                );
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
            'pluginName' => $settings->getDisplayName(),
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

        $nextRun = ScheduleHelper::calculateNext('daily');

        if ($nextRun !== null) {
            $delay = max(0, $nextRun->getTimestamp() - DateFormatHelper::now()->getTimestamp());
            $nextRunTime = DateFormatHelper::formatCompactDatetimeFromSettings(
                $nextRun,
                $settings,
                false,
                false,
            );
            $job = new self([
                'reschedule' => true,
                'nextRunTime' => $nextRunTime,
            ]);

            Craft::$app->getQueue()->delay($delay)->push($job);

            $this->logDebug('Scheduled next analytics cleanup', [
                'delay' => $delay,
                'nextRun' => $nextRunTime,
            ]);
        }
    }
}
