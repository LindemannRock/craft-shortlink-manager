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
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\queue\BaseJob;
use craft\queue\Queue;
use DateTime;
use DateTimeZone;
use lindemannrock\base\helpers\RecurringQueueHelper;
use lindemannrock\base\helpers\RecurringQueueResult;
use lindemannrock\base\queue\DeferredQueueJob;
use lindemannrock\base\queue\PortableQueueScheduler;
use lindemannrock\shortlinkmanager\jobs\CleanupAnalyticsJob;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\services\AnalyticsCleanupScheduler;
use lindemannrock\shortlinkmanager\services\AnalyticsService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionProperty;
use yii\queue\sqs\Queue as SqsQueue;

/**
 * Pins the portable recurring analytics-cleanup lifecycle.
 *
 * @since 5.20.0
 */
final class SchedulerPatternTest extends TestCase
{
    private ?Queue $originalQueue = null;

    private ?string $testQueueTable = null;

    private ?RecordingShortlinkSqsQueue $proxyQueue = null;

    private bool $timePaused = false;

    private AnalyticsCleanupScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->isolateQueue();
            $this->applySettingsForTest([
                'enableAnalytics' => true,
                'analyticsRetention' => 30,
            ]);
            $this->scheduler = ShortLinkManager::$plugin->analyticsCleanupScheduler;
            $this->scheduler->mutexTimeout = 5;
        } catch (\Throwable $exception) {
            $this->restoreQueue();
            throw $exception;
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->restoreQueue();
        } finally {
            parent::tearDown();
        }
    }

    public function testApprovedBaseRuntimeClassesResolveFromTheLocalCandidate(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $localBaseRoot = realpath($pluginRoot . '/../base');
        self::assertIsString($localBaseRoot);

        $classes = [
            RecurringQueueHelper::class => 'src/helpers/RecurringQueueHelper.php',
            PortableQueueScheduler::class => 'src/queue/PortableQueueScheduler.php',
            DeferredQueueJob::class => 'src/queue/DeferredQueueJob.php',
        ];

        foreach ($classes as $class => $relativePath) {
            $reflection = new ReflectionClass($class);
            $runtimeFile = $reflection->getFileName();
            self::assertIsString($runtimeFile);
            self::assertSame(realpath($localBaseRoot . '/' . $relativePath), realpath($runtimeFile), $class);
        }

        self::assertTrue(method_exists(PortableQueueScheduler::class, 'pushAt'));
        self::assertTrue(method_exists(RecurringQueueHelper::class, 'deletePending'));
    }

    public function testQueueIsolationHidesPermanentRowsAndUsesASeparateComponent(): void
    {
        self::assertNotNull($this->originalQueue);
        self::assertNotSame($this->originalQueue, Craft::$app->getQueue());
        self::assertSame(0, (int) (new Query())->from('{{%queue}}')->count());
        self::assertSame($this->originalQueue->tableName, Craft::$app->getQueue()->tableName);
    }

    public function testInitialScheduleTargetsNextMidnightWithNativeDelayPriorityAndTtr(): void
    {
        $target = $this->pauseBeforeMidnight(21_600);
        $result = $this->scheduler->bootstrap(ShortLinkManager::$plugin->getSettings());

        self::assertSame(RecurringQueueResult::STATUS_CREATED, $result->status);
        $row = $this->fetchOnlyOwnerRow();
        $job = $this->unserializeJob($row);
        self::assertInstanceOf(CleanupAnalyticsJob::class, $job);
        self::assertTrue($job->reschedule);
        self::assertSame(AnalyticsCleanupScheduler::RECURRING_OWNER, $job->recurringOwner);
        self::assertSame(21_600, (int) $row['delay']);
        self::assertSame(1024, (int) $row['priority']);
        self::assertSame(1800, (int) $row['ttr']);

        $nextRunTime = $this->scheduler->formatNextRun(
            (new DateTime('@' . $target))->setTimezone(new DateTimeZone(Craft::$app->getTimeZone())),
            ShortLinkManager::$plugin->getSettings(),
        );
        self::assertSame('ShortLink: Cleaning up old analytics (' . $nextRunTime . ')', $row['description']);
        self::assertSame([], $this->proxyDelays());
    }

    #[DataProvider('sqsBoundaryProvider')]
    public function testSqsBoundaryUsesDirectConsumerOnlyThroughNineHundredSeconds(
        int $remaining,
        string $expectedClass,
    ): void {
        $this->installQueueProxy(true);
        $target = $this->pauseBeforeMidnight($remaining);

        $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings());

        $row = $this->fetchOnlyOwnerRow();
        self::assertInstanceOf($expectedClass, $this->unserializeJob($row));
        self::assertSame(min(900, $remaining), (int) $row['delay']);
        self::assertSame([min(900, $remaining)], $this->proxyDelays());

        if ($remaining === 901) {
            $handoff = $this->unserializeJob($row);
            self::assertInstanceOf(DeferredQueueJob::class, $handoff);
            self::assertSame($target, $handoff->targetTimestamp);
            self::assertInstanceOf(CleanupAnalyticsJob::class, $handoff->job);
        }
    }

    /**
     * @return iterable<string, array{int, class-string}>
     */
    public static function sqsBoundaryProvider(): iterable
    {
        yield '900 seconds is direct' => [900, CleanupAnalyticsJob::class];
        yield '901 seconds starts a handoff' => [901, DeferredQueueJob::class];
    }

    public function testDailySqsScheduleUsesMultipleHandoffsWithoutEarlyCleanup(): void
    {
        $queue = $this->installQueueProxy(true);
        $analytics = new RecordingAnalyticsService();
        $this->swapPluginComponent('shortlink-manager', 'analytics', $analytics);
        $target = $this->pauseBeforeMidnight(3_601);

        $firstId = $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings())->jobId;
        self::assertNotNull($firstId);
        self::assertInstanceOf(DeferredQueueJob::class, $this->unserializeJob($this->fetchOnlyOwnerRow()));

        $this->pauseAt($target - 2_701);
        self::assertTrue($queue->executeJob($firstId));
        $secondRow = $this->fetchOnlyOwnerRow();
        self::assertInstanceOf(DeferredQueueJob::class, $this->unserializeJob($secondRow));
        self::assertSame(0, $analytics->cleanupCalls);

        $secondId = (string) $secondRow['id'];
        $this->pauseAt($target - 1_801);
        self::assertTrue($queue->executeJob($secondId));
        $thirdRow = $this->fetchOnlyOwnerRow();
        self::assertInstanceOf(DeferredQueueJob::class, $this->unserializeJob($thirdRow));
        self::assertSame(0, $analytics->cleanupCalls);

        $thirdId = (string) $thirdRow['id'];
        $this->pauseAt($target - 800);
        self::assertTrue($queue->executeJob($thirdId));
        $consumerRow = $this->fetchOnlyOwnerRow();
        self::assertInstanceOf(CleanupAnalyticsJob::class, $this->unserializeJob($consumerRow));
        self::assertSame(800, (int) $consumerRow['delay']);
        self::assertSame(1024, (int) $consumerRow['priority']);
        self::assertSame(1800, (int) $consumerRow['ttr']);
        self::assertSame(0, $analytics->cleanupCalls);
        self::assertSame([900, 900, 900, 800], $this->proxyDelays());
        self::assertLessThanOrEqual(900, max($this->proxyDelays()));
    }

    public function testLateHandoffDispatchesTheConsumerWithZeroDelay(): void
    {
        $queue = $this->installQueueProxy(true);
        $target = $this->pauseBeforeMidnight(901);
        $handoffId = $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings())->jobId;
        self::assertNotNull($handoffId);

        $this->pauseAt($target + 20);
        self::assertTrue($queue->executeJob($handoffId));

        $row = $this->fetchOnlyOwnerRow();
        self::assertInstanceOf(CleanupAnalyticsJob::class, $this->unserializeJob($row));
        self::assertSame(0, (int) $row['delay']);
        self::assertSame([900, 0], $this->proxyDelays());
    }

    public function testBootstrapAndReplayKeepExactlyOnePendingOwnerChain(): void
    {
        $this->pauseBeforeMidnight(21_600);
        $first = $this->scheduler->bootstrap(ShortLinkManager::$plugin->getSettings());
        $second = $this->scheduler->bootstrap(ShortLinkManager::$plugin->getSettings());
        $third = $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings());

        self::assertSame(RecurringQueueResult::STATUS_CREATED, $first->status);
        self::assertSame(RecurringQueueResult::STATUS_EXISTING, $second->status);
        self::assertSame(RecurringQueueResult::STATUS_EXISTING, $third->status);
        self::assertSame($first->jobId, $second->jobId);
        self::assertSame($first->jobId, $third->jobId);
        self::assertSame(1, $this->countOwnerRows());
    }

    #[DataProvider('initialSchedulingEntrypointProvider')]
    public function testPortableMutexBlocksInitialSchedulingWithoutPartialRows(string $entrypoint): void
    {
        $mutex = Craft::$app->getMutex();
        $this->scheduler->mutexTimeout = 0;
        self::assertTrue($mutex->acquire(AnalyticsCleanupScheduler::PORTABLE_MUTEX, 0));

        try {
            $this->expectRuntimeFailure(
                fn() => $entrypoint === 'bootstrap'
                    ? $this->scheduler->bootstrap(ShortLinkManager::$plugin->getSettings())
                    : $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings()),
                'Unable to acquire the analytics cleanup portable lock.',
            );
            self::assertSame(0, $this->countOwnerRows());
            self::assertSame(0, $this->countLegacyRows());
        } finally {
            $mutex->release(AnalyticsCleanupScheduler::PORTABLE_MUTEX);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function initialSchedulingEntrypointProvider(): iterable
    {
        yield 'bootstrap' => ['bootstrap'];
        yield 'ensure' => ['ensure'];
    }

    public function testDeferredContinuationSharesThePortableMutexWithInitialScheduling(): void
    {
        $queue = $this->installQueueProxy(true);
        $this->scheduler->mutexTimeout = 0;
        $this->pauseBeforeMidnight(901);
        $handoffId = $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings())->jobId;
        self::assertNotNull($handoffId);
        $handoff = $this->unserializeJob($this->fetchOnlyOwnerRow());
        self::assertInstanceOf(DeferredQueueJob::class, $handoff);
        self::assertSame(AnalyticsCleanupScheduler::PORTABLE_MUTEX, $handoff->mutexName);
        $this->markExecuting($queue, $handoffId);

        $mutex = Craft::$app->getMutex();
        self::assertTrue($mutex->acquire(AnalyticsCleanupScheduler::PORTABLE_MUTEX, 0));
        try {
            $this->expectRuntimeFailure(
                fn() => $handoff->execute($queue),
                'Unable to acquire the portable queue schedule lock.',
            );
            self::assertSame(1, $this->countOwnerRows());
            self::assertSame([900], $this->proxyDelays());
        } finally {
            $mutex->release(AnalyticsCleanupScheduler::PORTABLE_MUTEX);
            $this->markExecuting($queue, null);
        }
    }

    public function testDisabledBootstrapAlwaysUsesLockedCancellation(): void
    {
        $this->pauseBeforeMidnight(21_600);
        $jobId = $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings())->jobId;
        self::assertNotNull($jobId);
        $disabled = new Settings(['enableAnalytics' => false, 'analyticsRetention' => 30]);
        $mutex = Craft::$app->getMutex();
        $this->scheduler->mutexTimeout = 0;
        self::assertTrue($mutex->acquire(AnalyticsCleanupScheduler::PORTABLE_MUTEX, 0));

        try {
            $this->expectRuntimeFailure(
                fn() => $this->scheduler->bootstrap($disabled),
                'Unable to acquire the analytics cleanup portable lock.',
            );
            self::assertTrue($this->queueRowExists($jobId));
        } finally {
            $mutex->release(AnalyticsCleanupScheduler::PORTABLE_MUTEX);
        }

        self::assertSame(RecurringQueueResult::STATUS_SKIPPED, $this->scheduler->bootstrap($disabled)->status);
        self::assertFalse($this->queueRowExists($jobId));
    }

    public function testReservedExecutingJobCreatesExactlyOneSuccessorAcrossReplay(): void
    {
        $queue = Craft::$app->getQueue();
        $analytics = new RecordingAnalyticsService();
        $this->swapPluginComponent('shortlink-manager', 'analytics', $analytics);
        $this->pauseBeforeMidnight(21_600);
        $currentId = $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings())->jobId;
        self::assertNotNull($currentId);
        $job = $this->unserializeJob($this->fetchOnlyOwnerRow());
        self::assertInstanceOf(CleanupAnalyticsJob::class, $job);
        $this->markExecuting($queue, $currentId);

        $job->execute($queue);
        $job->execute($queue);

        self::assertSame(2, $this->countOwnerRows());
        self::assertSame(1, $this->countPendingOwnerRows());
        self::assertSame(2, $analytics->cleanupCalls);
        $this->markExecuting($queue, null);
    }

    public function testRetainedLegacyJobUpgradesItsSuccessorToTheNewOwner(): void
    {
        $queue = Craft::$app->getQueue();
        $analytics = new RecordingAnalyticsService();
        $this->swapPluginComponent('shortlink-manager', 'analytics', $analytics);
        $legacyId = $this->pushLegacyPhpJob();
        $legacy = $this->unserializeJob((new Query())->from('{{%queue}}')->where(['id' => $legacyId])->one());
        self::assertInstanceOf(CleanupAnalyticsJob::class, $legacy);
        self::assertSame('', $legacy->recurringOwner);
        self::assertSame($legacyId, $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings())->jobId);
        $this->markExecuting($queue, $legacyId);

        $legacy->execute($queue);

        self::assertSame(1, $analytics->cleanupCalls);
        self::assertSame(1, $this->countPendingOwnerRows());
        $successor = $this->ownerQuery()->andWhere(['fail' => false, 'timeUpdated' => null])->one();
        self::assertIsArray($successor);
        $successorJob = $this->unserializeJob($successor);
        self::assertInstanceOf(CleanupAnalyticsJob::class, $successorJob);
        self::assertSame(AnalyticsCleanupScheduler::RECURRING_OWNER, $successorJob->recurringOwner);
        $this->markExecuting($queue, null);
    }

    public function testDisabledReservedScheduleSkipsCleanupAndManualCleanupRemainsUsable(): void
    {
        $queue = Craft::$app->getQueue();
        $analytics = new RecordingAnalyticsService();
        $this->swapPluginComponent('shortlink-manager', 'analytics', $analytics);
        $this->pauseBeforeMidnight(21_600);
        $currentId = $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings())->jobId;
        self::assertNotNull($currentId);
        $scheduled = $this->unserializeJob($this->fetchOnlyOwnerRow());
        self::assertInstanceOf(CleanupAnalyticsJob::class, $scheduled);
        $this->markExecuting($queue, $currentId);

        $this->withSettings(['enableAnalytics' => false, 'analyticsRetention' => 30], function() use ($scheduled, $queue): void {
            $scheduled->execute($queue);
            (new CleanupAnalyticsJob(['reschedule' => false]))->execute($queue);
        });

        self::assertSame(1, $analytics->cleanupCalls);
        self::assertSame(0, $this->countPendingOwnerRows());
        $this->markExecuting($queue, null);
    }

    #[DataProvider('legacyPayloadProvider')]
    public function testLegacyPhpAndJsonRowsAreRetainedAsTheRecurringChain(string $format): void
    {
        $legacyId = $format === 'php'
            ? $this->pushLegacyPhpJob()
            : $this->insertJsonLegacyRow(true);

        $result = $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings());

        self::assertSame(RecurringQueueResult::STATUS_EXISTING, $result->status);
        self::assertSame($legacyId, $result->jobId);
        self::assertSame(1, $this->countLegacyRows());
        self::assertSame(0, $this->countOwnerRows());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function legacyPayloadProvider(): iterable
    {
        yield 'PHP serialization' => ['php'];
        yield 'JSON serialization' => ['json'];
    }

    public function testLegacyJobNestedInDeferredHandoffIsRecognized(): void
    {
        $legacy = new CleanupAnalyticsJob(['reschedule' => true]);
        $handoffId = $this->pushDeferredJob($legacy, ['shortlinkmanager', 'CleanupAnalyticsJob']);

        $result = $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings());

        self::assertSame($handoffId, $result->jobId);
        self::assertSame(1, $this->countLegacyRows());
        self::assertSame(0, $this->countOwnerRows());
    }

    public function testEarliestLegacyRowWinsAndCompetingOwnedChainIsRemoved(): void
    {
        $this->pauseBeforeMidnight(21_600);
        $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings());
        $laterLegacyId = $this->pushLegacyPhpJob(600);
        $earlierLegacyId = $this->pushLegacyPhpJob(300);

        $result = $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings());

        self::assertSame($earlierLegacyId, $result->jobId);
        self::assertSame(1, $this->countLegacyRows());
        self::assertFalse($this->queueRowExists($laterLegacyId));
        self::assertSame(0, $this->countOwnerRows());
    }

    public function testFailedLegacyRowDoesNotBlockRecoveryOrMatchManualCleanup(): void
    {
        $failedLegacyId = $this->pushLegacyPhpJob();
        $this->setQueueState($failedLegacyId, 'failed');
        $manualId = $this->pushManualCleanupJob();

        $result = $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings());

        self::assertSame(RecurringQueueResult::STATUS_CREATED, $result->status);
        self::assertSame(1, $this->countPendingOwnerRows());
        self::assertTrue($this->queueRowExists($failedLegacyId));
        self::assertTrue($this->queueRowExists($manualId));
        self::assertTrue($this->scheduler->hasPending());
    }

    public function testSettingsTransitionsEnableDisableReenableAndAvoidUnchangedChurn(): void
    {
        $enabled = new Settings(['enableAnalytics' => true, 'analyticsRetention' => 30]);
        $changedRetention = new Settings(['enableAnalytics' => true, 'analyticsRetention' => 120]);
        $disabled = new Settings(['enableAnalytics' => false, 'analyticsRetention' => 120]);

        $this->scheduler->reconcile(false, $enabled);
        $firstId = (string) $this->fetchOnlyOwnerRow()['id'];

        $this->scheduler->reconcile(true, $changedRetention);
        self::assertSame($firstId, (string) $this->fetchOnlyOwnerRow()['id']);

        $this->scheduler->reconcile(true, $disabled);
        self::assertSame(0, $this->countOwnerRows());

        $this->scheduler->reconcile(false, $enabled);
        self::assertSame(1, $this->countPendingOwnerRows());
    }

    public function testConfigOverridesControlTheEffectiveSettingsTransition(): void
    {
        $this->withSettings(['enableAnalytics' => false], function(): void {
            $effective = ShortLinkManager::$plugin->getSettings();
            self::assertFalse($this->scheduler->isEnabled($effective));
            $this->scheduler->reconcile(false, $effective);
            self::assertSame(0, $this->countOwnerRows());
        });

        $this->withSettings(['enableAnalytics' => true, 'analyticsRetention' => 0], function(): void {
            $effective = ShortLinkManager::$plugin->getSettings();
            self::assertFalse($this->scheduler->isEnabled($effective));
            $this->scheduler->reconcile(false, $effective);
            self::assertSame(0, $this->countOwnerRows());
        });
    }

    public function testCancellationRemovesEveryOwnedStateAndPreservesUnrelatedRows(): void
    {
        $ids = [];
        foreach (['pending', 'reserved', 'failed'] as $state) {
            $finalId = $this->pushOwnedFinalJob();
            $this->setQueueState($finalId, $state);
            $ids[] = $finalId;

            $handoffId = $this->pushDeferredJob(
                new CleanupAnalyticsJob([
                    'reschedule' => true,
                    'recurringOwner' => AnalyticsCleanupScheduler::RECURRING_OWNER,
                ]),
                [
                    AnalyticsCleanupScheduler::PLUGIN_TOKEN,
                    'CleanupAnalyticsJob',
                    AnalyticsCleanupScheduler::RECURRING_OWNER,
                ],
            );
            $this->setQueueState($handoffId, $state);
            $ids[] = $handoffId;

            $legacyId = $this->pushLegacyPhpJob();
            $this->setQueueState($legacyId, $state);
            $ids[] = $legacyId;
        }

        $manualId = $this->pushManualCleanupJob();
        $otherShortlinkId = $this->pushUnrelatedShortlinkJob();
        $otherPluginId = $this->insertOtherPluginCleanupRow();

        self::assertSame(9, $this->scheduler->cancel());
        foreach ($ids as $id) {
            self::assertFalse($this->queueRowExists($id));
        }
        self::assertTrue($this->queueRowExists($manualId));
        self::assertTrue($this->queueRowExists($otherShortlinkId));
        self::assertTrue($this->queueRowExists($otherPluginId));
    }

    public function testCancelledReservedHandoffCannotResurrectTheChain(): void
    {
        $queue = $this->installQueueProxy(true);
        $this->pauseBeforeMidnight(21_600);
        $handoffId = $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings())->jobId;
        self::assertNotNull($handoffId);
        $handoff = $this->unserializeJob($this->fetchOnlyOwnerRow());
        self::assertInstanceOf(DeferredQueueJob::class, $handoff);
        $this->markExecuting($queue, $handoffId);

        self::assertSame(1, $this->scheduler->cancel());
        $handoff->execute($queue);

        self::assertSame(0, $this->countOwnerRows());
        self::assertSame([900], $this->proxyDelays());
        $this->markExecuting($queue, null);
    }

    public function testPushAndMutexAndCancellationFailuresRemainObservable(): void
    {
        $this->installQueueProxy(true);
        self::assertNotNull($this->proxyQueue);
        $this->proxyQueue->failPushes = true;
        $this->pauseBeforeMidnight(21_600);

        try {
            $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings());
            self::fail('Expected the proxy push failure to propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Shortlink scheduler proxy failure.', $exception->getMessage());
        }
        self::assertSame(1, $this->countOwnerRows());

        $this->proxyQueue->failPushes = false;
        $this->scheduler->cancel();
        $mutex = Craft::$app->getMutex();
        $this->scheduler->mutexTimeout = 0;

        self::assertTrue($mutex->acquire(AnalyticsCleanupScheduler::LIFECYCLE_MUTEX, 0));
        try {
            $this->expectRuntimeFailure(
                fn() => $this->scheduler->reconcile(false, ShortLinkManager::$plugin->getSettings()),
                'Unable to acquire the analytics cleanup lifecycle lock.',
            );
        } finally {
            $mutex->release(AnalyticsCleanupScheduler::LIFECYCLE_MUTEX);
        }

        $this->scheduler->ensureScheduled(ShortLinkManager::$plugin->getSettings());
        self::assertTrue($mutex->acquire(AnalyticsCleanupScheduler::PORTABLE_MUTEX, 0));
        try {
            $this->expectRuntimeFailure(
                fn() => $this->scheduler->reconcile(true, new Settings([
                    'enableAnalytics' => false,
                    'analyticsRetention' => 30,
                ])),
                'Unable to acquire the analytics cleanup portable lock.',
            );
            self::assertSame(1, $this->countOwnerRows());
        } finally {
            $mutex->release(AnalyticsCleanupScheduler::PORTABLE_MUTEX);
        }
    }

    public function testSettingsControllerUsesSavedConfigAwareStateBeforeSuccess(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/controllers/SettingsController.php');
        self::assertIsString($source);

        $oldState = strpos($source, '$wasAnalyticsCleanupEnabled = $plugin->analyticsCleanupScheduler->isEnabled($currentSettings);');
        $save = strpos($source, 'if ($settings->saveToDatabase($attributesToValidate))');
        $reload = strpos($source, '$savedSettings = Settings::loadFromDatabase();');
        $config = strpos($source, "PluginHelper::applyConfigOverridesToSettings(\$savedSettings, 'shortlink-manager');");
        $reconcile = strpos($source, '$plugin->analyticsCleanupScheduler->reconcile($wasAnalyticsCleanupEnabled, $savedSettings);');
        $success = strpos($source, "\$this->setSuccessFlash(Craft::t('shortlink-manager', 'Settings saved.'));", $save ?: 0);

        foreach ([$oldState, $save, $reload, $config, $reconcile, $success] as $position) {
            self::assertIsInt($position);
        }
        self::assertTrue($oldState < $save && $save < $reload && $reload < $config && $config < $reconcile && $reconcile < $success);
    }

    public function testRuntimeHasNoCloudDependencyAndCustomerArchivesExpectTheScheduler(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $runtime = file_get_contents($pluginRoot . '/src/services/AnalyticsCleanupScheduler.php')
            . file_get_contents($pluginRoot . '/src/jobs/CleanupAnalyticsJob.php');
        $composer = file_get_contents($pluginRoot . '/composer.json');
        $archiveTest = file_get_contents($pluginRoot . '/tests/Integration/AssetDeliveryTest.php');

        self::assertIsString($runtime);
        self::assertIsString($composer);
        self::assertIsString($archiveTest);
        self::assertStringNotContainsString('craft\\cloud', $runtime);
        self::assertStringNotContainsString('craftcms/cloud', $composer);
        self::assertStringContainsString("'src/services/AnalyticsCleanupScheduler.php'", $archiveTest);
    }

    private function isolateQueue(): void
    {
        $queue = Craft::$app->getQueue();
        if (!$queue instanceof Queue) {
            throw new \RuntimeException('Scheduler integration tests require Craft\'s database queue.');
        }

        $this->originalQueue = $queue;
        $db = Craft::$app->getDb();
        $rawTable = $db->getSchema()->getRawTableName($queue->tableName);
        $this->testQueueTable = $rawTable;

        if ($db->getDriverName() === 'mysql') {
            $shadowTable = $rawTable . '_shortlink_test_' . bin2hex(random_bytes(8));
            $db->createCommand(sprintf(
                'CREATE TEMPORARY TABLE %s LIKE %s',
                $db->quoteTableName($shadowTable),
                $db->quoteTableName($rawTable),
            ))->execute();
            $db->createCommand(sprintf(
                'ALTER TABLE %s RENAME TO %s',
                $db->quoteTableName($shadowTable),
                $db->quoteTableName($rawTable),
            ))->execute();
        } elseif ($db->getDriverName() === 'pgsql') {
            $tableSchema = $db->getTableSchema($queue->tableName, true);
            if ($tableSchema === null) {
                throw new \RuntimeException('Unable to resolve Craft\'s permanent queue table.');
            }

            $db->createCommand(sprintf(
                'CREATE TEMPORARY TABLE %s (LIKE %s INCLUDING CONSTRAINTS INCLUDING INDEXES) ON COMMIT PRESERVE ROWS',
                $db->quoteTableName($rawTable),
                $db->quoteTableName($tableSchema->fullName),
            ))->execute();
            $db->createCommand(sprintf(
                'ALTER TABLE %s ALTER COLUMN [[id]] ADD GENERATED BY DEFAULT AS IDENTITY',
                $db->quoteTableName($rawTable),
            ))->execute();
        } else {
            throw new \RuntimeException('Scheduler queue isolation supports MySQL and PostgreSQL.');
        }

        Craft::$app->set('queue', $this->newQueue($queue, null));
    }

    private function installQueueProxy(bool $bounded): Queue
    {
        $current = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $current);
        $this->proxyQueue = $bounded ? new RecordingShortlinkSqsQueue() : null;
        $queue = $this->newQueue($current, $this->proxyQueue);
        Craft::$app->set('queue', $queue);

        return $queue;
    }

    private function newQueue(Queue $source, ?SqsQueue $proxyQueue): Queue
    {
        return new Queue([
            'db' => Craft::$app->getDb(),
            'mutex' => $source->mutex,
            'tableName' => $source->tableName,
            'channel' => $source->channel,
            'mutexTimeout' => $source->mutexTimeout,
            'proxyQueue' => $proxyQueue,
        ]);
    }

    private function restoreQueue(): void
    {
        if (isset($this->scheduler)) {
            $this->scheduler->mutexTimeout = 5;
        }

        if ($this->timePaused) {
            DateTimeHelper::resume();
            $this->timePaused = false;
        }

        $db = Craft::$app->getDb();

        try {
            if ($this->originalQueue !== null) {
                Craft::$app->set('queue', $this->originalQueue);
            }
        } finally {
            $this->originalQueue = null;
            $this->proxyQueue = null;

            if ($this->testQueueTable !== null) {
                $db->createCommand('DROP TEMPORARY TABLE IF EXISTS ' . $db->quoteTableName($this->testQueueTable))->execute();
                $this->testQueueTable = null;
            }
        }
    }

    private function pauseBeforeMidnight(int $seconds): int
    {
        $target = new DateTime('2035-01-02 00:00:00', new DateTimeZone(Craft::$app->getTimeZone()));
        $this->pauseAt($target->getTimestamp() - $seconds);

        return $target->getTimestamp();
    }

    private function pauseAt(int $timestamp): void
    {
        if ($this->timePaused) {
            DateTimeHelper::resume();
        }

        DateTimeHelper::pause(new DateTime('@' . $timestamp));
        $this->timePaused = true;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchOnlyOwnerRow(): array
    {
        $rows = $this->ownerQuery()->orderBy(['id' => SORT_ASC])->all();
        self::assertCount(1, $rows);

        return $rows[0];
    }

    private function ownerQuery(): Query
    {
        return (new Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', AnalyticsCleanupScheduler::PLUGIN_TOKEN])
            ->andWhere(['like', 'job', 'CleanupAnalyticsJob'])
            ->andWhere(['like', 'job', AnalyticsCleanupScheduler::RECURRING_OWNER]);
    }

    private function countOwnerRows(): int
    {
        return (int) $this->ownerQuery()->count();
    }

    private function countPendingOwnerRows(): int
    {
        return (int) $this->ownerQuery()->andWhere(['fail' => false, 'timeUpdated' => null])->count();
    }

    private function countLegacyRows(): int
    {
        return (int) (new Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', AnalyticsCleanupScheduler::PLUGIN_TOKEN])
            ->andWhere(['like', 'job', 'CleanupAnalyticsJob'])
            ->andWhere(['not like', 'job', AnalyticsCleanupScheduler::RECURRING_OWNER])
            ->andWhere(['or',
                ['like', 'job', '"reschedule";b:1'],
                ['like', 'job', '"reschedule":true'],
            ])
            ->count();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function unserializeJob(array $row): object
    {
        $queue = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $queue);
        $job = $queue->serializer->unserialize((string) $row['job']);
        self::assertIsObject($job);

        return $job;
    }

    private function pushLegacyPhpJob(int $delay = 300): string
    {
        $jobId = Craft::$app->getQueue()->delay($delay)->push(new CleanupAnalyticsJob([
            'reschedule' => true,
        ]));
        self::assertNotNull($jobId);

        return $jobId;
    }

    private function insertJsonLegacyRow(bool $reschedule): string
    {
        return $this->insertRawQueueRow(json_encode([
            'plugin' => AnalyticsCleanupScheduler::PLUGIN_TOKEN,
            'class' => 'CleanupAnalyticsJob',
            'reschedule' => $reschedule,
        ], JSON_THROW_ON_ERROR));
    }

    private function insertOtherPluginCleanupRow(): string
    {
        return $this->insertRawQueueRow('{"plugin":"otherplugin","class":"CleanupAnalyticsJob","reschedule":true}');
    }

    private function insertRawQueueRow(string $payload): string
    {
        $queue = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $queue);
        Craft::$app->getDb()->createCommand()->insert('{{%queue}}', [
            'channel' => $queue->channel ?? 'queue',
            'job' => $payload,
            'description' => 'Scheduler test payload',
            'timePushed' => DateTimeHelper::currentTimeStamp(),
            'ttr' => 300,
            'delay' => 300,
            'priority' => 1024,
        ])->execute();

        return (string) Craft::$app->getDb()->getLastInsertID();
    }

    private function pushOwnedFinalJob(): string
    {
        $jobId = Craft::$app->getQueue()->delay(300)->push(new CleanupAnalyticsJob([
            'reschedule' => true,
            'recurringOwner' => AnalyticsCleanupScheduler::RECURRING_OWNER,
        ]));
        self::assertNotNull($jobId);

        return $jobId;
    }

    /**
     * @param list<string> $identityTokens
     */
    private function pushDeferredJob(CleanupAnalyticsJob $job, array $identityTokens): string
    {
        $jobId = Craft::$app->getQueue()->delay(300)->push(new DeferredQueueJob([
            'job' => $job,
            'targetTimestamp' => DateTimeHelper::currentTimeStamp() + 1_800,
            'identityTokens' => $identityTokens,
            'mutexName' => AnalyticsCleanupScheduler::PORTABLE_MUTEX,
            'mutexTimeout' => 5,
            'priority' => null,
            'ttr' => null,
            'chainId' => bin2hex(random_bytes(16)),
        ]));
        self::assertNotNull($jobId);

        return $jobId;
    }

    private function pushManualCleanupJob(): string
    {
        $jobId = Craft::$app->getQueue()->delay(0)->push(new CleanupAnalyticsJob([
            'reschedule' => false,
        ]));
        self::assertNotNull($jobId);

        return $jobId;
    }

    private function pushUnrelatedShortlinkJob(): string
    {
        $jobId = Craft::$app->getQueue()->delay(0)->push(new UnrelatedShortlinkQueueJob());
        self::assertNotNull($jobId);

        return $jobId;
    }

    private function setQueueState(string $jobId, string $state): void
    {
        $attributes = match ($state) {
            'pending' => ['fail' => false, 'timeUpdated' => null],
            'reserved' => ['fail' => false, 'timeUpdated' => DateTimeHelper::currentTimeStamp()],
            'failed' => ['fail' => true, 'timeUpdated' => DateTimeHelper::currentTimeStamp()],
            default => throw new \InvalidArgumentException("Unknown queue state: $state"),
        };

        Craft::$app->getDb()->createCommand()->update('{{%queue}}', $attributes, ['id' => $jobId])->execute();
    }

    private function queueRowExists(string $jobId): bool
    {
        return (new Query())->from('{{%queue}}')->where(['id' => $jobId])->exists();
    }

    private function markExecuting(Queue $queue, ?string $jobId): void
    {
        if ($jobId !== null) {
            $this->setQueueState($jobId, 'reserved');
        }

        $property = new ReflectionProperty(Queue::class, '_executingJobId');
        $property->setValue($queue, $jobId);
    }

    /**
     * @return list<int>
     */
    private function proxyDelays(): array
    {
        return $this->proxyQueue === null ? [] : array_column($this->proxyQueue->pushes, 'delay');
    }

    private function expectRuntimeFailure(callable $callback, string $message): void
    {
        try {
            $callback();
            self::fail('Expected runtime failure: ' . $message);
        } catch (\RuntimeException $exception) {
            self::assertSame($message, $exception->getMessage());
        }
    }
}

/**
 * Records SQS proxy delays without contacting AWS.
 */
final class RecordingShortlinkSqsQueue extends SqsQueue
{
    /**
     * @var list<array{delay: int, priority: mixed, ttr: int}>
     */
    public array $pushes = [];

    public bool $failPushes = false;

    protected function pushMessage($message, $ttr, $delay, $priority): string
    {
        if ($this->failPushes) {
            throw new \RuntimeException('Shortlink scheduler proxy failure.');
        }

        $this->pushes[] = [
            'delay' => (int) $delay,
            'priority' => $priority,
            'ttr' => (int) $ttr,
        ];

        return 'shortlink-scheduler-proxy-' . count($this->pushes);
    }
}

/**
 * Records analytics cleanup executions.
 */
final class RecordingAnalyticsService extends AnalyticsService
{
    public int $cleanupCalls = 0;

    public function cleanupOldAnalytics(): int
    {
        $this->cleanupCalls++;

        return 0;
    }
}

/**
 * Unrelated Shortlink queue fixture used to verify cancellation boundaries.
 */
final class UnrelatedShortlinkQueueJob extends BaseJob
{
    public string $pluginToken = 'shortlinkmanager';

    public function execute($queue): void
    {
    }
}
