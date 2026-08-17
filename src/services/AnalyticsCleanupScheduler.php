<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\DateTimeHelper as CraftDateTimeHelper;
use DateTime;
use DateTimeZone;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\RecurringQueueHelper;
use lindemannrock\base\helpers\RecurringQueueResult;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\base\queue\PortableQueueScheduler;
use lindemannrock\shortlinkmanager\jobs\CleanupAnalyticsJob;
use lindemannrock\shortlinkmanager\models\Settings;
use yii\db\Expression;

/**
 * Owns the recurring analytics-cleanup queue lifecycle.
 *
 * @since 5.29.0
 */
final class AnalyticsCleanupScheduler extends Component
{
    public const PLUGIN_TOKEN = 'shortlinkmanager';
    public const RECURRING_OWNER = 'shortlink-manager:analytics-cleanup:daily';
    public const LIFECYCLE_MUTEX = 'shortlink-manager:analytics-cleanup:schedule';
    public const PORTABLE_MUTEX = 'shortlink-manager:analytics-cleanup:portable';

    public int $mutexTimeout = 5;

    public function bootstrap(Settings $settings): RecurringQueueResult
    {
        if ($this->isEnabled($settings)) {
            return $this->ensureScheduled($settings);
        }

        $this->cancel();

        return new RecurringQueueResult(RecurringQueueResult::STATUS_SKIPPED);
    }

    public function ensureScheduled(Settings $settings): RecurringQueueResult
    {
        if (!$this->isEnabled($settings)) {
            return new RecurringQueueResult(RecurringQueueResult::STATUS_SKIPPED);
        }

        return $this->withScheduleLocks(fn(): RecurringQueueResult => $this->ensureScheduledUnderLocks($settings));
    }

    public function reconcile(bool $wasEnabled, Settings $savedSettings): void
    {
        $isEnabled = $this->isEnabled($savedSettings);
        if ($wasEnabled === $isEnabled) {
            return;
        }

        $this->withScheduleLocks(function() use ($isEnabled, $savedSettings): void {
            if ($isEnabled) {
                $this->ensureScheduledUnderLocks($savedSettings);
            } else {
                $this->cancelUnderLocks();
            }
        });
    }

    public function cancel(): int
    {
        return $this->withScheduleLocks(fn(): int => $this->cancelUnderLocks());
    }

    public function hasPending(): bool
    {
        return RecurringQueueHelper::hasPending(
            self::PLUGIN_TOKEN,
            CleanupAnalyticsJob::class,
            [self::RECURRING_OWNER],
        ) || $this->healthyLegacyRows() !== [];
    }

    public function isEnabled(Settings $settings): bool
    {
        return $settings->enableAnalytics && $settings->analyticsRetention > 0;
    }

    public function nextRun(): ?DateTime
    {
        return ScheduleHelper::calculateNext(
            'daily',
            CraftDateTimeHelper::now(new DateTimeZone(Craft::$app->getTimeZone())),
        );
    }

    public function formatNextRun(DateTime $nextRun, Settings $settings): string
    {
        return DateFormatHelper::formatCompactDatetimeFromSettings(
            $nextRun,
            $settings,
            null,
            false,
            pluginHandle: 'shortlink-manager',
        );
    }

    private function ensureScheduledUnderLocks(Settings $settings): RecurringQueueResult
    {
        $legacyRows = $this->healthyLegacyRows();
        if ($legacyRows !== []) {
            $keptId = (string) $legacyRows[0]['id'];
            $duplicatesDeleted = $this->deleteRows(array_slice($legacyRows, 1));
            $duplicatesDeleted += $this->deleteRows($this->newOwnerRows());

            return new RecurringQueueResult(
                RecurringQueueResult::STATUS_EXISTING,
                $keptId,
                $duplicatesDeleted,
            );
        }

        $ownedRows = $this->healthyNewOwnerRows();
        if ($ownedRows !== []) {
            $keptId = (string) $ownedRows[0]['id'];

            return new RecurringQueueResult(
                RecurringQueueResult::STATUS_EXISTING,
                $keptId,
                $this->deleteRows(array_slice($ownedRows, 1)),
            );
        }

        $nextRun = $this->nextRun();
        if ($nextRun === null) {
            return new RecurringQueueResult(RecurringQueueResult::STATUS_SKIPPED);
        }

        $nextRunTime = $this->formatNextRun($nextRun, $settings);
        $jobId = PortableQueueScheduler::pushAt(
            job: new CleanupAnalyticsJob([
                'reschedule' => true,
                'recurringOwner' => self::RECURRING_OWNER,
                'nextRunTime' => $nextRunTime,
            ]),
            targetTimestamp: $nextRun->getTimestamp(),
            identityTokens: [
                self::PLUGIN_TOKEN,
                'CleanupAnalyticsJob',
                self::RECURRING_OWNER,
            ],
            mutexName: self::PORTABLE_MUTEX,
            mutexTimeout: $this->mutexTimeout,
        );

        return new RecurringQueueResult(RecurringQueueResult::STATUS_CREATED, $jobId);
    }

    private function cancelUnderLocks(): int
    {
        return $this->deleteRows($this->newOwnerRows()) + $this->deleteRows($this->legacyRows());
    }

    /**
     * Serialize lifecycle mutations with Base deferred-handoff continuation.
     *
     * The lock order is always lifecycle then portable. Callbacks run while
     * both locks are held and must not reacquire either mutex.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withScheduleLocks(callable $callback): mixed
    {
        $mutex = Craft::$app->getMutex();
        if (!$mutex->acquire(self::LIFECYCLE_MUTEX, $this->mutexTimeout)) {
            throw new \RuntimeException('Unable to acquire the analytics cleanup lifecycle lock.');
        }

        try {
            if (!$mutex->acquire(self::PORTABLE_MUTEX, $this->mutexTimeout)) {
                throw new \RuntimeException('Unable to acquire the analytics cleanup portable lock.');
            }

            try {
                return $callback();
            } finally {
                $mutex->release(self::PORTABLE_MUTEX);
            }
        } finally {
            $mutex->release(self::LIFECYCLE_MUTEX);
        }
    }

    /**
     * @return list<array{id: int|string}>
     */
    private function healthyLegacyRows(): array
    {
        /** @var list<array{id: int|string}> $rows */
        $rows = $this->legacyQuery()
            ->andWhere(['fail' => false, 'timeUpdated' => null])
            ->orderBy(new Expression('[[timePushed]] + [[delay]] ASC'))
            ->addOrderBy(['priority' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $rows;
    }

    /**
     * @return list<array{id: int|string}>
     */
    private function legacyRows(): array
    {
        /** @var list<array{id: int|string}> $rows */
        $rows = $this->legacyQuery()->all();

        return $rows;
    }

    private function legacyQuery(): Query
    {
        return (new Query())
            ->select(['id'])
            ->from('{{%queue}}')
            ->where(['like', 'job', self::PLUGIN_TOKEN])
            ->andWhere(['like', 'job', 'CleanupAnalyticsJob'])
            ->andWhere(['not like', 'job', self::RECURRING_OWNER])
            ->andWhere(['or',
                ['like', 'job', '"reschedule";b:1'],
                ['like', 'job', '"reschedule":true'],
            ]);
    }

    /**
     * @return list<array{id: int|string}>
     */
    private function newOwnerRows(): array
    {
        /** @var list<array{id: int|string}> $rows */
        $rows = (new Query())
            ->select(['id'])
            ->from('{{%queue}}')
            ->where(['like', 'job', self::PLUGIN_TOKEN])
            ->andWhere(['like', 'job', 'CleanupAnalyticsJob'])
            ->andWhere(['like', 'job', self::RECURRING_OWNER])
            ->all();

        return $rows;
    }

    /**
     * @return list<array{id: int|string}>
     */
    private function healthyNewOwnerRows(): array
    {
        /** @var list<array{id: int|string}> $rows */
        $rows = (new Query())
            ->select(['id'])
            ->from('{{%queue}}')
            ->where(['like', 'job', self::PLUGIN_TOKEN])
            ->andWhere(['like', 'job', 'CleanupAnalyticsJob'])
            ->andWhere(['like', 'job', self::RECURRING_OWNER])
            ->andWhere(['fail' => false, 'timeUpdated' => null])
            ->orderBy(new Expression('[[timePushed]] + [[delay]] ASC'))
            ->addOrderBy(['priority' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $rows;
    }

    /**
     * @param list<array{id: int|string}> $rows
     */
    private function deleteRows(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $ids = array_map(static fn(array $row): string => (string) $row['id'], $rows);

        return Craft::$app->getDb()->createCommand()
            ->delete('{{%queue}}', ['id' => $ids])
            ->execute();
    }
}
