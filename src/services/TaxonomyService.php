<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services;

use craft\base\Component;
use craft\helpers\StringHelper;
use lindemannrock\shortlinkmanager\records\FolderRecord;
use lindemannrock\shortlinkmanager\records\ShortLinkTagRecord;
use lindemannrock\shortlinkmanager\records\TagRecord;

/**
 * Taxonomy service for plugin-internal folders/tags.
 */
class TaxonomyService extends Component
{
    /**
     * @var array<int, string>
     */
    private array $folderNameCache = [];

    /**
     * @return array<int, string>
     */
    public function getFolderOptions(): array
    {
        $folders = FolderRecord::find()
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->all();

        $options = [];
        foreach ($folders as $folder) {
            if (!$folder instanceof FolderRecord) {
                continue;
            }
            $options[(int)$folder->id] = (string)$folder->name;
        }

        return $options;
    }

    /**
     * @param int $shortLinkId
     * @return array<int, string>
     */
    public function getTagNamesForShortLink(int $shortLinkId): array
    {
        $rows = (new \craft\db\Query())
            ->select(['t.name'])
            ->from('{{%shortlinkmanager_shortlink_tags}} st')
            ->innerJoin('{{%shortlinkmanager_tags}} t', '[[t.id]] = [[st.tagId]]')
            ->where(['st.shortLinkId' => $shortLinkId])
            ->orderBy(['t.name' => SORT_ASC])
            ->column();

        return array_values(array_unique(array_filter(array_map(static fn($name) => trim((string)$name), $rows))));
    }

    /**
     * @param int|null $folderId
     * @return string|null
     */
    public function getFolderNameById(?int $folderId): ?string
    {
        if (!$folderId) {
            return null;
        }

        if (array_key_exists($folderId, $this->folderNameCache)) {
            return $this->folderNameCache[$folderId] ?: null;
        }

        $record = FolderRecord::find()->where(['id' => $folderId])->one();
        $name = $record instanceof FolderRecord ? (string)$record->name : '';
        $this->folderNameCache[$folderId] = $name;

        return $name ?: null;
    }

    /**
     * @return array<int, string>
     */
    public function getAllTagNames(): array
    {
        $rows = (new \craft\db\Query())
            ->select(['t.name'])
            ->distinct()
            ->from('{{%shortlinkmanager_tags}} t')
            ->innerJoin('{{%shortlinkmanager_shortlink_tags}} st', '[[st.tagId]] = [[t.id]]')
            ->orderBy(['t.name' => SORT_ASC])
            ->column();

        return array_values(array_unique(array_filter(array_map(static fn($name) => trim((string)$name), $rows))));
    }

    /**
     * @param array<int, string> $names
     * @return array<int, int>
     */
    public function ensureTagsByNames(array $names): array
    {
        $tagIds = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $slug = StringHelper::toKebabCase($name);
            if ($slug === '') {
                $slug = strtolower(preg_replace('/\s+/', '-', $name) ?? $name);
            }

            $record = TagRecord::find()->where(['slug' => $slug])->one();
            if (!$record) {
                $record = new TagRecord();
                $record->name = $name;
                $record->slug = $slug;
                $record->sortOrder = 0;
                $record->save(false);
            }

            if ($record instanceof TagRecord) {
                $tagIds[] = (int)$record->id;
            }
        }

        return array_values(array_unique($tagIds));
    }

    /**
     * @param int $shortLinkId
     * @param array<int, string> $tagNames
     */
    public function syncShortLinkTagsByNames(int $shortLinkId, array $tagNames): void
    {
        $targetTagIds = $this->ensureTagsByNames($tagNames);

        $existingTagIds = (new \craft\db\Query())
            ->select(['tagId'])
            ->from('{{%shortlinkmanager_shortlink_tags}}')
            ->where(['shortLinkId' => $shortLinkId])
            ->column();
        $existingTagIds = array_map('intval', $existingTagIds);

        $toDelete = array_diff($existingTagIds, $targetTagIds);
        if (!empty($toDelete)) {
            ShortLinkTagRecord::deleteAll([
                'shortLinkId' => $shortLinkId,
                'tagId' => $toDelete,
            ]);
            $this->cleanupUnusedTags(array_values(array_map('intval', $toDelete)));
        }

        $toInsert = array_diff($targetTagIds, $existingTagIds);
        foreach ($toInsert as $tagId) {
            $pivot = new ShortLinkTagRecord();
            $pivot->shortLinkId = $shortLinkId;
            $pivot->tagId = (int)$tagId;
            $pivot->save(false);
        }

        // Ensure previously orphaned tags from older states are also cleaned.
        $this->cleanupUnusedTags();
    }

    /**
     * Delete tags that are no longer linked to any shortlink.
     * Folders are intentionally not cleaned up automatically.
     *
     * @param array<int, int>|null $candidateTagIds Optional subset to check.
     */
    public function cleanupUnusedTags(?array $candidateTagIds = null): void
    {
        $query = (new \craft\db\Query())
            ->select(['t.id'])
            ->from('{{%shortlinkmanager_tags}} t')
            ->leftJoin('{{%shortlinkmanager_shortlink_tags}} st', '[[st.tagId]] = [[t.id]]')
            ->where(['st.id' => null]);

        if (!empty($candidateTagIds)) {
            $candidateTagIds = array_values(array_unique(array_map('intval', $candidateTagIds)));
            $query->andWhere(['t.id' => $candidateTagIds]);
        }

        $orphanIds = array_map('intval', $query->column());
        if (!empty($orphanIds)) {
            TagRecord::deleteAll(['id' => $orphanIds]);
        }
    }

    /**
     * @param string $name
     * @return int
     */
    public function getOrCreateFolderByName(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }

        $slug = StringHelper::toKebabCase($name);
        if ($slug === '') {
            $slug = strtolower(preg_replace('/\s+/', '-', $name) ?? $name);
        }

        $record = FolderRecord::find()->where(['slug' => $slug])->one();
        if (!$record) {
            $record = new FolderRecord();
            $record->name = $name;
            $record->slug = $slug;
            $record->parentId = null;
            $record->sortOrder = 0;
            $record->save(false);
        }

        return $record instanceof FolderRecord ? (int)$record->id : 0;
    }
}
