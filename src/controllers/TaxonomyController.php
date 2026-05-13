<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 */

namespace lindemannrock\shortlinkmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\Response;

class TaxonomyController extends Controller
{
    /**
     * List folders and tags.
     *
     * Follows the canonical CP table index-page pattern (in-memory variant) —
     * see plugins/base/docs/template-guides/cp-table-index-pattern.md.
     * Controller owns query-param parsing, allowlist validation, filter, and
     * pagination; the Twig template stays presentational.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('shortLinkManager:manageTaxonomy');

        $request = Craft::$app->getRequest();
        $settings = ShortLinkManager::$plugin->getSettings();
        $taxonomy = ShortLinkManager::$plugin->taxonomy;

        // ---- Param parsing + allowlist validation -------------------------

        $view = (string) $request->getQueryParam('view', 'folders');
        $validViews = ['folders', 'tags'];
        if (!in_array($view, $validViews, true)) {
            $view = 'folders';
        }

        // 64-char defensive clamp on free-text search.
        $search = trim((string) $request->getQueryParam('search', ''));
        if (mb_strlen($search) > 64) {
            $search = mb_substr($search, 0, 64);
        }

        $validSortFields = ['name', 'usageCount'];
        $sort = (string) $request->getParam('sort', 'name');
        if (!in_array($sort, $validSortFields, true)) {
            $sort = 'name';
        }
        $dir = strtolower((string) $request->getParam('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // ---- Load + filter ------------------------------------------------

        $items = $view === 'tags'
            ? $taxonomy->getTagsForIndex()
            : $taxonomy->getFoldersForIndex();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $items = array_values(array_filter(
                $items,
                static fn(array $i): bool =>
                    str_contains(mb_strtolower($i['name']), $needle)
                    || str_contains(mb_strtolower($i['slug']), $needle)
            ));
        }

        // ---- Sort + paginate ----------------------------------------------

        $items = $this->sortItems($items, $sort, $dir);

        $totalCount = count($items);
        $page = max(1, (int) $request->getParam('page', 1));
        $limit = max(1, (int) $settings->itemsPerPage);
        $offset = ($page - 1) * $limit;
        $items = array_slice($items, $offset, $limit);

        return $this->renderTemplate('shortlink-manager/taxonomy/index', [
            'currentView' => $view,
            'items' => $items,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'limit' => $limit,
            'totalCount' => $totalCount,
            'canCreate' => Craft::$app->getUser()->checkPermission('shortLinkManager:createTaxonomy'),
            'canEdit' => Craft::$app->getUser()->checkPermission('shortLinkManager:editTaxonomy'),
            'canDelete' => Craft::$app->getUser()->checkPermission('shortLinkManager:deleteTaxonomy'),
        ]);
    }

    /**
     * Sort folder/tag rows in PHP. Both views share the
     * {id, name, slug, usageCount} shape, so one helper covers both.
     *
     * The sort-field allowlist is enforced in actionIndex() before we land
     * here, so the default branch is reached only on a logic bug.
     *
     * @param array<int, array{id:int, name:string, slug:string, usageCount:int}> $items
     * @return array<int, array{id:int, name:string, slug:string, usageCount:int}>
     */
    private function sortItems(array $items, string $sort, string $dir): array
    {
        $multiplier = $dir === 'desc' ? -1 : 1;

        usort($items, function(array $a, array $b) use ($sort, $multiplier): int {
            $cmp = match ($sort) {
                'usageCount' => $a['usageCount'] <=> $b['usageCount'],
                default => strcasecmp($a['name'], $b['name']),
            };

            // Stable tie-break by name so equal usage counts don't shuffle
            // between requests — keeps pagination predictable.
            if ($cmp === 0 && $sort !== 'name') {
                $cmp = strcasecmp($a['name'], $b['name']);
            }

            return $cmp * $multiplier;
        });

        return $items;
    }

    public function actionEditFolder(int $folderId): Response
    {
        $this->requirePermission('shortLinkManager:editTaxonomy');

        $folder = ShortLinkManager::$plugin->taxonomy->getFolderById($folderId);
        if (!$folder) {
            throw new \yii\web\NotFoundHttpException(Craft::t('shortlink-manager', 'Folder not found.'));
        }

        return $this->renderTemplate('shortlink-manager/taxonomy/edit-folder', [
            'folder' => $folder,
            'isNew' => false,
        ]);
    }

    public function actionNewFolder(): Response
    {
        $this->requirePermission('shortLinkManager:createTaxonomy');

        $folder = ShortLinkManager::$plugin->taxonomy->createFolderRecord();

        return $this->renderTemplate('shortlink-manager/taxonomy/edit-folder', [
            'folder' => $folder,
            'isNew' => true,
        ]);
    }

    public function actionEditTag(int $tagId): Response
    {
        $this->requirePermission('shortLinkManager:editTaxonomy');

        $tag = ShortLinkManager::$plugin->taxonomy->getTagById($tagId);
        if (!$tag) {
            throw new \yii\web\NotFoundHttpException(Craft::t('shortlink-manager', 'Tag not found.'));
        }

        return $this->renderTemplate('shortlink-manager/taxonomy/edit-tag', [
            'tag' => $tag,
            'isNew' => false,
        ]);
    }

    public function actionNewTag(): Response
    {
        $this->requirePermission('shortLinkManager:createTaxonomy');

        $tag = ShortLinkManager::$plugin->taxonomy->createTagRecord();

        return $this->renderTemplate('shortlink-manager/taxonomy/edit-tag', [
            'tag' => $tag,
            'isNew' => true,
        ]);
    }

    public function actionSaveFolder(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:editTaxonomy');

        $taxonomy = ShortLinkManager::$plugin->taxonomy;
        $folderId = (int)$this->request->getBodyParam('folderId', 0);
        $name = trim((string)$this->request->getBodyParam('name', ''));

        $folder = $folderId > 0
            ? $taxonomy->getFolderById($folderId)
            : $taxonomy->createFolderRecord();

        if (!$folder) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Folder not found.'));
            return $this->redirectToPostedUrl();
        }

        if (!$taxonomy->saveFolder($folder, $name)) {
            Craft::$app->getSession()->setError($folder->getFirstError('name') ?: Craft::t('shortlink-manager', 'Could not save folder.'));
            return $this->redirectToPostedUrl();
        }

        Craft::$app->getElements()->invalidateCachesForElementType(ShortLink::class);
        Craft::$app->getSession()->setNotice(
            $folderId > 0
                ? Craft::t('shortlink-manager', 'Folder renamed.')
                : Craft::t('shortlink-manager', 'Folder created.')
        );

        return $this->redirectToPostedUrl();
    }

    public function actionSaveTag(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:editTaxonomy');

        $taxonomy = ShortLinkManager::$plugin->taxonomy;
        $tagId = (int)$this->request->getBodyParam('tagId', 0);
        $name = trim((string)$this->request->getBodyParam('name', ''));

        $tag = $tagId > 0
            ? $taxonomy->getTagById($tagId)
            : $taxonomy->createTagRecord();

        if (!$tag) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Tag not found.'));
            return $this->redirectToPostedUrl();
        }

        if (!$taxonomy->saveTag($tag, $name)) {
            Craft::$app->getSession()->setError($tag->getFirstError('name') ?: Craft::t('shortlink-manager', 'Could not save tag.'));
            return $this->redirectToPostedUrl();
        }

        Craft::$app->getElements()->invalidateCachesForElementType(ShortLink::class);
        Craft::$app->getSession()->setNotice(
            $tagId > 0
                ? Craft::t('shortlink-manager', 'Tag renamed.')
                : Craft::t('shortlink-manager', 'Tag created.')
        );

        return $this->redirectToPostedUrl();
    }

    public function actionDeleteFolder(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:deleteTaxonomy');

        $taxonomy = ShortLinkManager::$plugin->taxonomy;
        $folderId = (int)$this->request->getRequiredBodyParam('folderId');
        $folder = $taxonomy->getFolderById($folderId);
        if (!$folder) {
            $message = Craft::t('shortlink-manager', 'Folder not found.');
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($message);
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirectToPostedUrl();
        }

        if ($taxonomy->deleteFoldersByIds([$folderId]) !== 1) {
            $message = Craft::t('shortlink-manager', 'Could not delete folder.');
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($message);
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirectToPostedUrl();
        }

        Craft::$app->getElements()->invalidateCachesForElementType(ShortLink::class);
        $message = Craft::t('shortlink-manager', 'Folder deleted.');
        if ($this->request->getAcceptsJson()) {
            return $this->asSuccess($message);
        }
        Craft::$app->getSession()->setNotice($message);
        return $this->redirectToPostedUrl();
    }

    public function actionBulkDeleteFolders(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:deleteTaxonomy');

        $taxonomy = ShortLinkManager::$plugin->taxonomy;
        $folderIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array)$this->request->getBodyParam('folderIds', [])
        ))));

        if ($folderIds === []) {
            $message = Craft::t('shortlink-manager', 'No folders selected.');
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($message);
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirectToPostedUrl();
        }

        $deletedCount = $taxonomy->deleteFoldersByIds($folderIds);

        if ($deletedCount === 0) {
            $message = Craft::t('shortlink-manager', 'Could not delete folders.');
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($message);
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirectToPostedUrl();
        }

        Craft::$app->getElements()->invalidateCachesForElementType(ShortLink::class);
        $message = Craft::t('shortlink-manager', 'Deleted {count} folder(s).', ['count' => $deletedCount]);
        if ($this->request->getAcceptsJson()) {
            return $this->asSuccess($message, ['count' => $deletedCount]);
        }
        Craft::$app->getSession()->setNotice($message);
        return $this->redirectToPostedUrl();
    }

    public function actionDeleteTag(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:deleteTaxonomy');

        $taxonomy = ShortLinkManager::$plugin->taxonomy;
        $tagId = (int)$this->request->getRequiredBodyParam('tagId');
        $tag = $taxonomy->getTagById($tagId);
        if (!$tag) {
            $message = Craft::t('shortlink-manager', 'Tag not found.');
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($message);
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirectToPostedUrl();
        }

        if ($taxonomy->deleteTagsByIds([$tagId]) !== 1) {
            $message = Craft::t('shortlink-manager', 'Could not delete tag.');
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($message);
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirectToPostedUrl();
        }

        Craft::$app->getElements()->invalidateCachesForElementType(ShortLink::class);
        $message = Craft::t('shortlink-manager', 'Tag deleted.');
        if ($this->request->getAcceptsJson()) {
            return $this->asSuccess($message);
        }
        Craft::$app->getSession()->setNotice($message);
        return $this->redirectToPostedUrl();
    }

    public function actionBulkDeleteTags(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:deleteTaxonomy');

        $taxonomy = ShortLinkManager::$plugin->taxonomy;
        $tagIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array)$this->request->getBodyParam('tagIds', [])
        ))));

        if ($tagIds === []) {
            $message = Craft::t('shortlink-manager', 'No tags selected.');
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($message);
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirectToPostedUrl();
        }

        $deletedCount = $taxonomy->deleteTagsByIds($tagIds);

        if ($deletedCount === 0) {
            $message = Craft::t('shortlink-manager', 'Could not delete tags.');
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($message);
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirectToPostedUrl();
        }

        Craft::$app->getElements()->invalidateCachesForElementType(ShortLink::class);
        $message = Craft::t('shortlink-manager', 'Deleted {count} tag(s).', ['count' => $deletedCount]);
        if ($this->request->getAcceptsJson()) {
            return $this->asSuccess($message, ['count' => $deletedCount]);
        }
        Craft::$app->getSession()->setNotice($message);
        return $this->redirectToPostedUrl();
    }
}
