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
}
