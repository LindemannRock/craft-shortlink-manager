<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use lindemannrock\base\helpers\SlugHandleHelper;
use lindemannrock\shortlinkmanager\records\FolderRecord;
use lindemannrock\shortlinkmanager\records\ShortLinkTagRecord;
use lindemannrock\shortlinkmanager\records\TagRecord;

/**
 * Taxonomy service for plugin-internal folders/tags.
 *
 * @since 5.15.0
 */
class TaxonomyService extends Component
{
    /**
     * @var array<int, string>
     */
    private array $folderNameCache = [];

    /**
     * @var array<string, int>
     */
    private array $folderIdBySlugCache = [];

    /**
     * @var array<string, int>
     */
    private array $tagIdBySlugCache = [];

    public function createFolderRecord(): FolderRecord
    {
        $folder = new FolderRecord();
        $folder->name = '';
        $folder->slug = '';
        $folder->parentId = null;
        $folder->sortOrder = 0;

        return $folder;
    }

    public function createTagRecord(): TagRecord
    {
        $tag = new TagRecord();
        $tag->name = '';
        $tag->slug = '';
        $tag->sortOrder = 0;

        return $tag;
    }

    public function getFolderById(int $folderId): ?FolderRecord
    {
        return FolderRecord::findOne(['id' => $folderId]);
    }

    public function getTagById(int $tagId): ?TagRecord
    {
        return TagRecord::findOne(['id' => $tagId]);
    }

    /**
     * @return array<int, array{id:int, name:string, slug:string, usageCount:int}>
     */
    public function getFoldersForIndex(): array
    {
        $rows = (new Query())
            ->select([
                'f.id',
                'f.name',
                'f.slug',
                'usageCount' => 'COUNT(sl.id)',
            ])
            ->from('{{%shortlinkmanager_folders}} f')
            ->leftJoin('{{%shortlinkmanager}} sl', '[[sl.folderId]] = [[f.id]]')
            ->groupBy(['f.id', 'f.name', 'f.slug', 'f.sortOrder'])
            ->orderBy(['f.sortOrder' => SORT_ASC, 'f.name' => SORT_ASC])
            ->all();

        return array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'slug' => (string)$row['slug'],
            'usageCount' => (int)$row['usageCount'],
        ], $rows);
    }

    /**
     * @return array<int, array{id:int, name:string, slug:string, usageCount:int}>
     */
    public function getTagsForIndex(): array
    {
        $rows = (new Query())
            ->select([
                't.id',
                't.name',
                't.slug',
                'usageCount' => 'COUNT(st.id)',
            ])
            ->from('{{%shortlinkmanager_tags}} t')
            ->leftJoin('{{%shortlinkmanager_shortlink_tags}} st', '[[st.tagId]] = [[t.id]]')
            ->groupBy(['t.id', 't.name', 't.slug', 't.sortOrder'])
            ->orderBy(['t.sortOrder' => SORT_ASC, 't.name' => SORT_ASC])
            ->all();

        return array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'slug' => (string)$row['slug'],
            'usageCount' => (int)$row['usageCount'],
        ], $rows);
    }

    /**
     * @return array<int, string>
     */
    public function getFolderOptions(): array
    {
        $options = [];
        foreach ($this->getFoldersForIndex() as $folder) {
            $options[$folder['id']] = $folder['name'];
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

        return $this->normalizeTagNames($rows);
    }

    /**
     * Batch-fetch tag names for multiple shortlinks in a single query.
     *
     * @param array<int, int> $shortLinkIds
     * @return array<int, array<int, string>> Keyed by shortLinkId
     */
    public function getTagNamesForShortLinks(array $shortLinkIds): array
    {
        if (empty($shortLinkIds)) {
            return [];
        }

        $rows = (new \craft\db\Query())
            ->select(['st.shortLinkId', 't.name'])
            ->from('{{%shortlinkmanager_shortlink_tags}} st')
            ->innerJoin('{{%shortlinkmanager_tags}} t', '[[t.id]] = [[st.tagId]]')
            ->where(['st.shortLinkId' => $shortLinkIds])
            ->orderBy(['t.name' => SORT_ASC])
            ->all();

        $result = array_fill_keys($shortLinkIds, []);
        foreach ($rows as $row) {
            $id = (int)$row['shortLinkId'];
            $name = trim((string)$row['name']);
            if ($name !== '' && isset($result[$id])) {
                $result[$id][] = $name;
            }
        }

        foreach ($result as &$names) {
            $names = $this->normalizeTagNames($names);
        }
        unset($names);

        return $result;
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
            ->from('{{%shortlinkmanager_tags}} t')
            ->orderBy(['t.sortOrder' => SORT_ASC, 't.name' => SORT_ASC])
            ->column();

        return $this->normalizeTagNames($rows);
    }

    /**
     * @param array<int, mixed> $names
     * @return array<int, string>
     */
    public function normalizeTagNames(array $names): array
    {
        $normalized = [];

        foreach ($names as $name) {
            if (!is_scalar($name)) {
                continue;
            }

            $value = $this->normalizeTaxonomyName((string)$name);
            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<int, string> $names
     * @return array<int, int>
     */
    public function ensureTagsByNames(array $names): array
    {
        $tagIds = [];
        foreach ($this->normalizeTagNames($names) as $name) {
            $slug = $this->buildSlug($name);
            if ($slug === '') {
                continue;
            }

            if (array_key_exists($slug, $this->tagIdBySlugCache)) {
                $tagIds[] = $this->tagIdBySlugCache[$slug];
                continue;
            }

            $record = TagRecord::find()->where(['slug' => $slug])->one();
            if (!$record) {
                $record = $this->createTagRecord();
                if (!$this->saveTag($record, $name)) {
                    continue;
                }
            }

            if ($record instanceof TagRecord) {
                $tagId = (int)$record->id;
                $this->tagIdBySlugCache[$slug] = $tagId;
                $tagIds[] = $tagId;
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
        $targetTagIds = $this->ensureTagsByNames($this->normalizeTagNames($tagNames));

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
        }

        $toInsert = array_diff($targetTagIds, $existingTagIds);
        foreach ($toInsert as $tagId) {
            $pivot = new ShortLinkTagRecord();
            $pivot->shortLinkId = $shortLinkId;
            $pivot->tagId = (int)$tagId;
            $pivot->save(false);
        }
    }

    public function saveFolder(FolderRecord $folder, string $name): bool
    {
        $normalizedName = $this->normalizeTaxonomyName($name);
        if ($normalizedName === '') {
            $folder->addError('name', Craft::t('shortlink-manager', 'Folder name cannot be empty.'));
            return false;
        }

        $slug = $this->buildSlug($normalizedName);
        if ($slug === '') {
            $folder->addError('name', Craft::t('shortlink-manager', 'Invalid folder name.'));
            return false;
        }

        $existingFolder = FolderRecord::find()
            ->where(['slug' => $slug])
            ->andWhere(['not', ['id' => (int)($folder->id ?? 0)]])
            ->one();
        if ($existingFolder) {
            $folder->addError('name', Craft::t('shortlink-manager', 'Folder name already exists.'));
            return false;
        }

        $oldSlug = (string)($folder->getOldAttribute('slug') ?? '');

        $folder->name = $normalizedName;
        $folder->slug = $slug;
        $folder->parentId = $folder->parentId ?: null;
        $folder->sortOrder = (int)($folder->sortOrder ?? 0);

        if (!$folder->save()) {
            if (!$folder->hasErrors('name')) {
                $folder->addError('name', Craft::t('shortlink-manager', 'Could not save folder.'));
            }
            return false;
        }

        $this->folderNameCache[(int)$folder->id] = $folder->name;
        $this->folderIdBySlugCache[$slug] = (int)$folder->id;
        if ($oldSlug !== '' && $oldSlug !== $slug) {
            unset($this->folderIdBySlugCache[$oldSlug]);
        }

        return true;
    }

    public function saveTag(TagRecord $tag, string $name): bool
    {
        $normalizedName = $this->normalizeTaxonomyName($name);
        if ($normalizedName === '') {
            $tag->addError('name', Craft::t('shortlink-manager', 'Tag name cannot be empty.'));
            return false;
        }

        $slug = $this->buildSlug($normalizedName);
        if ($slug === '') {
            $tag->addError('name', Craft::t('shortlink-manager', 'Invalid tag name.'));
            return false;
        }

        $existingTag = TagRecord::find()
            ->where(['slug' => $slug])
            ->andWhere(['not', ['id' => (int)($tag->id ?? 0)]])
            ->one();
        if ($existingTag) {
            $tag->addError('name', Craft::t('shortlink-manager', 'Tag name already exists.'));
            return false;
        }

        $oldSlug = (string)($tag->getOldAttribute('slug') ?? '');

        $tag->name = $normalizedName;
        $tag->slug = $slug;
        $tag->sortOrder = (int)($tag->sortOrder ?? 0);

        if (!$tag->save()) {
            if (!$tag->hasErrors('name')) {
                $tag->addError('name', Craft::t('shortlink-manager', 'Could not save tag.'));
            }
            return false;
        }

        $this->tagIdBySlugCache[$slug] = (int)$tag->id;
        if ($oldSlug !== '' && $oldSlug !== $slug) {
            unset($this->tagIdBySlugCache[$oldSlug]);
        }

        return true;
    }

    /**
     * @param array<int, int> $folderIds
     */
    public function deleteFoldersByIds(array $folderIds): int
    {
        $deletedCount = 0;

        foreach (FolderRecord::findAll(['id' => array_values(array_unique(array_map('intval', $folderIds)))]) as $folder) {
            if ($folder->delete()) {
                unset($this->folderNameCache[(int)$folder->id]);
                unset($this->folderIdBySlugCache[(string)$folder->slug]);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * @param array<int, int> $tagIds
     */
    public function deleteTagsByIds(array $tagIds): int
    {
        $deletedCount = 0;

        foreach (TagRecord::findAll(['id' => array_values(array_unique(array_map('intval', $tagIds)))]) as $tag) {
            if ($tag->delete()) {
                unset($this->tagIdBySlugCache[(string)$tag->slug]);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * @param string $name
     * @return int
     */
    public function getOrCreateFolderByName(string $name): int
    {
        $name = $this->normalizeTaxonomyName($name);
        if ($name === '') {
            return 0;
        }

        $slug = $this->buildSlug($name);
        if ($slug === '') {
            return 0;
        }

        if (array_key_exists($slug, $this->folderIdBySlugCache)) {
            return $this->folderIdBySlugCache[$slug];
        }

        $record = FolderRecord::find()->where(['slug' => $slug])->one();
        if (!$record) {
            $record = $this->createFolderRecord();
            if (!$this->saveFolder($record, $name)) {
                return 0;
            }
        }

        if ($record instanceof FolderRecord) {
            $folderId = (int)$record->id;
            $this->folderIdBySlugCache[$slug] = $folderId;

            return $folderId;
        }

        return 0;
    }

    private function normalizeTaxonomyName(string $name): string
    {
        return mb_substr(trim($name), 0, 255);
    }

    private function buildSlug(string $name): string
    {
        return SlugHandleHelper::normalizeSlug($name, '');
    }
}
