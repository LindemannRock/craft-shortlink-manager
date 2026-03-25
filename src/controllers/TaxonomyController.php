<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 */

namespace lindemannrock\shortlinkmanager\controllers;

use Craft;
use craft\db\Query;
use craft\helpers\StringHelper;
use craft\web\Controller;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\records\FolderRecord;
use lindemannrock\shortlinkmanager\records\TagRecord;
use yii\web\Response;

class TaxonomyController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requirePermission('shortLinkManager:editLinks');

        $folders = (new Query())
            ->select([
                'f.id',
                'f.name',
                'f.slug',
                'usageCount' => 'COUNT(sl.id)',
            ])
            ->from('{{%shortlinkmanager_folders}} f')
            ->leftJoin('{{%shortlinkmanager}} sl', '[[sl.folderId]] = [[f.id]]')
            ->groupBy(['f.id', 'f.name', 'f.slug'])
            ->orderBy(['f.name' => SORT_ASC])
            ->all();

        $tags = (new Query())
            ->select([
                't.id',
                't.name',
                't.slug',
                'usageCount' => 'COUNT(st.id)',
            ])
            ->from('{{%shortlinkmanager_tags}} t')
            ->leftJoin('{{%shortlinkmanager_shortlink_tags}} st', '[[st.tagId]] = [[t.id]]')
            ->groupBy(['t.id', 't.name', 't.slug'])
            ->orderBy(['t.name' => SORT_ASC])
            ->all();

        return $this->renderTemplate('shortlink-manager/taxonomy/index', [
            'folders' => $folders,
            'tags' => $tags,
        ]);
    }

    public function actionEditFolder(int $folderId): Response
    {
        $this->requirePermission('shortLinkManager:editLinks');

        $folder = FolderRecord::findOne(['id' => $folderId]);
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
        $this->requirePermission('shortLinkManager:editLinks');

        $folder = new FolderRecord();
        $folder->id = 0;
        $folder->name = '';
        $folder->slug = '';

        return $this->renderTemplate('shortlink-manager/taxonomy/edit-folder', [
            'folder' => $folder,
            'isNew' => true,
        ]);
    }

    public function actionEditTag(int $tagId): Response
    {
        $this->requirePermission('shortLinkManager:editLinks');

        $tag = TagRecord::findOne(['id' => $tagId]);
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
        $this->requirePermission('shortLinkManager:editLinks');

        $tag = new TagRecord();
        $tag->id = 0;
        $tag->name = '';
        $tag->slug = '';

        return $this->renderTemplate('shortlink-manager/taxonomy/edit-tag', [
            'tag' => $tag,
            'isNew' => true,
        ]);
    }

    public function actionSaveFolder(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('shortLinkManager:editLinks');

        $folderId = (int)$this->request->getBodyParam('folderId', 0);
        $name = trim((string)$this->request->getBodyParam('name', ''));

        if ($name === '') {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Folder name cannot be empty.'));
            return $this->redirectToPostedUrl();
        }

        $slug = StringHelper::toKebabCase($name);
        if ($slug === '') {
            $slug = strtolower((string)preg_replace('/\s+/', '-', $name));
        }

        if ($slug === '') {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Invalid folder name.'));
            return $this->redirectToPostedUrl();
        }

        $exists = FolderRecord::find()
            ->where(['slug' => $slug])
            ->andWhere(['not', ['id' => $folderId]])
            ->exists();
        if ($exists) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Folder name already exists.'));
            return $this->redirectToPostedUrl();
        }

        $folder = $folderId > 0
            ? FolderRecord::findOne(['id' => $folderId])
            : new FolderRecord();

        if (!$folder) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Folder not found.'));
            return $this->redirectToPostedUrl();
        }

        $folder->name = $name;
        $folder->slug = $slug;
        if (!$folder->save()) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Could not save folder.'));
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
        $this->requirePermission('shortLinkManager:editLinks');

        $tagId = (int)$this->request->getBodyParam('tagId', 0);
        $name = trim((string)$this->request->getBodyParam('name', ''));

        if ($name === '') {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Tag name cannot be empty.'));
            return $this->redirectToPostedUrl();
        }

        $slug = StringHelper::toKebabCase($name);
        if ($slug === '') {
            $slug = strtolower((string)preg_replace('/\s+/', '-', $name));
        }

        if ($slug === '') {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Invalid tag name.'));
            return $this->redirectToPostedUrl();
        }

        $exists = TagRecord::find()
            ->where(['slug' => $slug])
            ->andWhere(['not', ['id' => $tagId]])
            ->exists();
        if ($exists) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Tag name already exists.'));
            return $this->redirectToPostedUrl();
        }

        $tag = $tagId > 0
            ? TagRecord::findOne(['id' => $tagId])
            : new TagRecord();

        if (!$tag) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Tag not found.'));
            return $this->redirectToPostedUrl();
        }

        $tag->name = $name;
        $tag->slug = $slug;
        if (!$tag->save()) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Could not save tag.'));
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
        $this->requirePermission('shortLinkManager:editLinks');

        $folderId = (int)$this->request->getRequiredBodyParam('folderId');
        $folder = FolderRecord::findOne(['id' => $folderId]);
        if (!$folder) {
            $message = Craft::t('shortlink-manager', 'Folder not found.');
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($message);
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirectToPostedUrl();
        }

        if (!$folder->delete()) {
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
        $this->requirePermission('shortLinkManager:editLinks');

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

        $deletedCount = 0;
        foreach (FolderRecord::findAll(['id' => $folderIds]) as $folder) {
            if ($folder->delete()) {
                $deletedCount++;
            }
        }

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
        $this->requirePermission('shortLinkManager:editLinks');

        $tagId = (int)$this->request->getRequiredBodyParam('tagId');
        $tag = TagRecord::findOne(['id' => $tagId]);
        if (!$tag) {
            $message = Craft::t('shortlink-manager', 'Tag not found.');
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($message);
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirectToPostedUrl();
        }

        if (!$tag->delete()) {
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
        $this->requirePermission('shortLinkManager:editLinks');

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

        $deletedCount = 0;
        foreach (TagRecord::findAll(['id' => $tagIds]) as $tag) {
            if ($tag->delete()) {
                $deletedCount++;
            }
        }

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
