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
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins the contract for slug generation and validation in
 * {@see \lindemannrock\shortlinkmanager\services\ShortLinksService}.
 *
 * Covers:
 *  - `generateUniqueSlug()` — length default + uniqueness across calls
 *  - `isReservedSlug()` — case-insensitive match against settings list
 *  - `validateSlug()` — rejects reserved AND existing slugs, honours
 *    `$excludeId` so saving an unchanged slug doesn't false-positive
 *
 * @since 5.19.0
 */
final class SlugGenerationTest extends TestCase
{
    public function testGenerateUniqueSlugReturnsRequestedLengthByDefault(): void
    {
        $slug = $this->shortLinks->generateUniqueSlug(10);

        $this->assertSame(10, strlen($slug), 'Slug should match the requested length when uniqueness is achievable.');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $slug, 'Slug should be CSPRNG-alphanumeric.');
    }

    public function testGenerateUniqueSlugReturnsDistinctValuesAcrossCalls(): void
    {
        $a = $this->shortLinks->generateUniqueSlug(12);
        $b = $this->shortLinks->generateUniqueSlug(12);
        $c = $this->shortLinks->generateUniqueSlug(12);

        $this->assertNotSame($a, $b);
        $this->assertNotSame($b, $c);
        $this->assertNotSame($a, $c);
    }

    public function testIsReservedSlugMatchesCaseInsensitively(): void
    {
        /** @var Settings $settings */
        $settings = ShortLinkManager::$plugin->getSettings();
        $previous = $settings->reservedCodes;
        $settings->reservedCodes = ['admin', 'api'];

        try {
            $this->assertTrue($this->shortLinks->isReservedSlug('admin'));
            $this->assertTrue($this->shortLinks->isReservedSlug('ADMIN'), 'Reserved slug match must be case-insensitive.');
            $this->assertTrue($this->shortLinks->isReservedSlug('Api'));
            $this->assertFalse($this->shortLinks->isReservedSlug('public'));
        } finally {
            $settings->reservedCodes = $previous;
        }
    }

    public function testValidateSlugRejectsReservedSlug(): void
    {
        /** @var Settings $settings */
        $settings = ShortLinkManager::$plugin->getSettings();
        $previous = $settings->reservedCodes;
        $settings->reservedCodes = ['login'];

        try {
            $this->assertFalse($this->shortLinks->validateSlug('login'));
            $this->assertFalse($this->shortLinks->validateSlug('LOGIN'));
        } finally {
            $settings->reservedCodes = $previous;
        }
    }

    public function testValidateSlugRejectsAlreadyPersistedSlug(): void
    {
        $existing = $this->seedShortLink();

        $this->assertFalse($this->shortLinks->validateSlug($existing->slug), 'Reusing a persisted slug must fail validation.');
    }

    public function testValidateSlugHonoursExcludeId(): void
    {
        $existing = $this->seedShortLink();

        // Saving the same shortlink without changing its slug must not
        // trip the uniqueness check — that's what `$excludeId` is for.
        $this->assertTrue(
            $this->shortLinks->validateSlug($existing->slug, $existing->id),
            'validateSlug() must skip the row identified by $excludeId.',
        );
    }

    public function testDuplicatingShortLinkUsesHyphenatedUniqueSlug(): void
    {
        $existing = $this->seedShortLink([
            'code' => 'sl-test-duplicate',
            'slug' => 'sl-test-duplicate',
        ]);
        $this->seedShortLink([
            'code' => 'sl-test-duplicate-1',
            'slug' => 'sl-test-duplicate-1',
        ]);

        $duplicate = Craft::$app->getElements()->duplicateElement($existing);

        $this->assertInstanceOf(ShortLink::class, $duplicate);
        $this->assertSame('sl-test-duplicate-2', $duplicate->slug);
    }
}
