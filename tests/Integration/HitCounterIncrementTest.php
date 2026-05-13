<?php

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use Craft;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins the contract for {@see \lindemannrock\shortlinkmanager\services\ShortLinksService::incrementHits()}.
 *
 * Audit item 3.9 (HIGH) tracked a race condition in the previous
 * `['hits' => $shortLink->hits + 1]` form — two concurrent requests could
 * read the same value and both write `n+1`, losing a count. The fix moved
 * the increment into an atomic SQL expression. This test pins the new
 * behaviour so a regression to the old shape would fail in CI.
 *
 * @since 5.19.0
 */
final class HitCounterIncrementTest extends TestCase
{
    public function testIncrementHitsPersistsToDatabase(): void
    {
        $link = $this->seedShortLink();
        $this->assertSame(0, $this->fetchHitsFromDb($link->id));

        $this->shortLinks->incrementHits($link);

        $this->assertSame(1, $this->fetchHitsFromDb($link->id));
    }

    public function testIncrementHitsUpdatesInMemoryModel(): void
    {
        $link = $this->seedShortLink();
        $this->assertSame(0, $link->hits);

        $this->shortLinks->incrementHits($link);

        $this->assertSame(1, $link->hits, 'In-memory model should track the increment too.');
    }

    public function testMultipleIncrementsAccumulate(): void
    {
        $link = $this->seedShortLink();

        for ($i = 0; $i < 5; $i++) {
            $this->shortLinks->incrementHits($link);
        }

        $this->assertSame(5, $this->fetchHitsFromDb($link->id));
    }

    public function testIncrementUsesAtomicExpressionAndDoesNotClobberConcurrentWrites(): void
    {
        $link = $this->seedShortLink();
        $this->assertSame(0, $link->hits, 'Seeded model starts at 0 hits.');

        // Simulate a concurrent request advancing the DB column while the
        // current in-memory model still believes `hits = 0`. The atomic
        // `[[hits]] + 1` SQL expression must read the *current* DB value,
        // not the stale in-memory one — proving the fix for audit #3.9.
        Craft::$app->getDb()
            ->createCommand()
            ->update('{{%shortlinkmanager}}', ['hits' => 100], ['id' => $link->id])
            ->execute();

        // Sanity: stale model still thinks 0, DB says 100.
        $this->assertSame(0, $link->hits);
        $this->assertSame(100, $this->fetchHitsFromDb($link->id));

        $this->shortLinks->incrementHits($link);

        // If the old `$shortLink->hits + 1` shape regressed in, the DB
        // would now hold `1` (clobbering the concurrent +100). The atomic
        // expression instead reads from disk and lands on 101.
        $this->assertSame(101, $this->fetchHitsFromDb($link->id), 'Atomic SQL expression must read fresh value from disk.');
    }
}
