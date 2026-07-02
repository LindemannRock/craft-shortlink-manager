<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\shortlinkmanager\records\FolderRecord;
use lindemannrock\shortlinkmanager\records\TagRecord;
use lindemannrock\shortlinkmanager\services\TaxonomyService;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.26.0
 */
#[CoversClass(TaxonomyService::class)]
final class TaxonomyServiceMemoizationTest extends TestCase
{
    public function testRepeatedTagNameResolutionUsesRequestCache(): void
    {
        $name = 'Memo Tag ' . bin2hex(random_bytes(4));
        $service = new TaxonomyService();

        try {
            $tagIds = $service->ensureTagsByNames([$name]);
            self::assertCount(1, $tagIds);

            $cache = $this->readCache($service, 'tagIdBySlugCache');
            self::assertCount(1, $cache);
            self::assertSame($tagIds[0], reset($cache));

            self::assertSame($tagIds, $service->ensureTagsByNames([$name]));
            self::assertSame($cache, $this->readCache($service, 'tagIdBySlugCache'));
        } finally {
            TagRecord::deleteAll(['name' => $name]);
        }
    }

    public function testRepeatedFolderNameResolutionUsesRequestCache(): void
    {
        $name = 'Memo Folder ' . bin2hex(random_bytes(4));
        $service = new TaxonomyService();

        try {
            $folderId = $service->getOrCreateFolderByName($name);
            self::assertGreaterThan(0, $folderId);

            $cache = $this->readCache($service, 'folderIdBySlugCache');
            self::assertCount(1, $cache);
            self::assertSame($folderId, reset($cache));

            self::assertSame($folderId, $service->getOrCreateFolderByName($name));
            self::assertSame($cache, $this->readCache($service, 'folderIdBySlugCache'));
        } finally {
            FolderRecord::deleteAll(['name' => $name]);
        }
    }

    /**
     * @return array<string, int>
     */
    private function readCache(TaxonomyService $service, string $propertyName): array
    {
        $property = new \ReflectionProperty($service, $propertyName);
        $property->setAccessible(true);
        $value = $property->getValue($service);
        self::assertIsArray($value);

        return $value;
    }
}
