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
use craft\console\Application as ConsoleApplication;
use craft\db\Query;
use craft\helpers\Db;
use craft\web\Request;
use craft\web\Session;
use lindemannrock\shortlinkmanager\controllers\TaxonomyController;
use lindemannrock\shortlinkmanager\records\FolderRecord;
use lindemannrock\shortlinkmanager\records\TagRecord;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

#[CoversClass(TaxonomyController::class)]
final class DirectTaxonomyAuthorizationTest extends TestCase
{
    private mixed $originalRequest = null;
    private mixed $originalResponse = null;
    private ?ConsoleApplication $originalApplication = null;

    protected function tearDown(): void
    {
        if ($this->originalApplication !== null) {
            Craft::$app = $this->originalApplication;
            \Yii::$app = $this->originalApplication;
            if ($this->originalRequest !== null) {
                $this->originalApplication->set('request', $this->originalRequest);
            }
            if ($this->originalResponse !== null) {
                $this->originalApplication->set('response', $this->originalResponse);
            }
        }

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, list<string>, string, bool}>
     */
    public static function permissionMatrix(): iterable
    {
        $create = 'shortLinkManager:createTaxonomy';
        $edit = 'shortLinkManager:editTaxonomy';

        foreach (['folder', 'tag'] as $type) {
            yield "{$type} create with create only" => [$type, [$create], 'create', true];
            yield "{$type} update with create only" => [$type, [$create], 'update', false];
            yield "{$type} create with edit only" => [$type, [$edit], 'create', false];
            yield "{$type} update with edit only" => [$type, [$edit], 'update', true];
            yield "{$type} create with both" => [$type, [$create, $edit], 'create', true];
            yield "{$type} update with both" => [$type, [$create, $edit], 'update', true];
            yield "{$type} create with neither" => [$type, [], 'create', false];
            yield "{$type} update with neither" => [$type, [], 'update', false];
        }
    }

    /** @param list<string> $permissions */
    #[DataProvider('permissionMatrix')]
    public function testDirectCreateAndUpdateUseTheirDedicatedPermissions(
        string $type,
        array $permissions,
        string $operation,
        bool $allowed,
    ): void {
        $marker = $this->taxonomyMarker("{$type}-{$operation}");
        $neighborName = $marker . '-neighbor';
        $targetName = $marker . '-target';
        $updatedName = $marker . '-updated';
        $neighborId = $this->seedTaxonomy($type, $neighborName);
        $targetId = $operation === 'update' ? $this->seedTaxonomy($type, $targetName) : null;
        $before = $this->stateSnapshot($type, $neighborId, $targetId);

        try {
            $body = ['name' => $operation === 'create' ? $targetName : $updatedName];
            if ($operation === 'update') {
                $body[$this->idParam($type)] = $targetId;
            }

            $controller = $this->controller($permissions, $body);
            $thrown = null;
            try {
                $this->save($controller, $type);
            } catch (ForbiddenHttpException $exception) {
                $thrown = $exception;
            }

            if ($allowed) {
                self::assertNull($thrown, 'The intended taxonomy operation must be authorized.');
            } else {
                self::assertInstanceOf(ForbiddenHttpException::class, $thrown);
            }

            $expectedPermission = $operation === 'create'
                ? 'shortLinkManager:createTaxonomy'
                : 'shortLinkManager:editTaxonomy';
            self::assertSame([$expectedPermission], $controller->requiredPermissions);

            if ($operation === 'create' && $allowed) {
                self::assertSame(1, $this->countTaxonomyByName($type, $targetName));
            } elseif ($operation === 'create') {
                self::assertSame(0, $this->countTaxonomyByName($type, $targetName));
            } elseif ($allowed) {
                self::assertSame(0, $this->countTaxonomyByName($type, $targetName));
                self::assertSame(1, $this->countTaxonomyByName($type, $updatedName));
                self::assertSame($targetId, $this->taxonomyIdByName($type, $updatedName));
            } else {
                self::assertSame($targetName, $this->taxonomyNameById($type, (int)$targetId));
            }

            $after = $this->stateSnapshot($type, $neighborId, $targetId);
            self::assertSame($before['links'], $after['links']);
            self::assertSame($before['otherTaxonomy'], $after['otherTaxonomy']);
            self::assertSame($before['neighbor'], $after['neighbor']);
        } finally {
            $this->cleanupTaxonomyNames($type, [$neighborName, $targetName, $updatedName]);
        }
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidUpdateIds(): iterable
    {
        foreach (['folder', 'tag'] as $type) {
            yield "{$type} malformed text" => [$type, 'not-an-id'];
            yield "{$type} negative" => [$type, '-1'];
            yield "{$type} unknown" => [$type, 2147483647];
        }
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function createIds(): iterable
    {
        foreach (['folder', 'tag'] as $type) {
            yield "{$type} omitted" => [$type, null];
            yield "{$type} blank" => [$type, ''];
            yield "{$type} zero integer" => [$type, 0];
            yield "{$type} zero string" => [$type, '0'];
        }
    }

    #[DataProvider('createIds')]
    public function testAbsentBlankAndZeroIdentifiersUseCreatePermission(string $type, mixed $submittedId): void
    {
        $name = $this->taxonomyMarker("{$type}-create-id");
        $body = ['name' => $name];
        if ($submittedId !== null) {
            $body[$this->idParam($type)] = $submittedId;
        }

        try {
            $controller = $this->controller(['shortLinkManager:createTaxonomy'], $body);
            $this->save($controller, $type);

            self::assertSame(['shortLinkManager:createTaxonomy'], $controller->requiredPermissions);
            self::assertSame(1, $this->countTaxonomyByName($type, $name));
        } finally {
            $this->cleanupTaxonomyNames($type, [$name]);
        }
    }

    #[DataProvider('invalidUpdateIds')]
    public function testInvalidUpdateIdsNeverBecomeCreates(string $type, mixed $submittedId): void
    {
        $marker = $this->taxonomyMarker("{$type}-invalid-id");
        $name = $marker . '-unexpected';

        try {
            $createOnlyController = $this->controller(
                ['shortLinkManager:createTaxonomy'],
                [$this->idParam($type) => $submittedId, 'name' => $name],
            );
            try {
                $this->save($createOnlyController, $type);
                self::fail('A malformed update intent must not enter the create permission branch.');
            } catch (ForbiddenHttpException) {
                self::assertSame(['shortLinkManager:editTaxonomy'], $createOnlyController->requiredPermissions);
                self::assertSame(0, $this->countTaxonomyByName($type, $name));
            }

            $controller = $this->controller(
                ['shortLinkManager:editTaxonomy'],
                [$this->idParam($type) => $submittedId, 'name' => $name],
            );
            $this->save($controller, $type);

            self::assertSame(['shortLinkManager:editTaxonomy'], $controller->requiredPermissions);
            self::assertSame(0, $this->countTaxonomyByName($type, $name));
            self::assertNotNull(Craft::$app->getSession()->getFlash('error'));
        } finally {
            $this->cleanupTaxonomyNames($type, [$name]);
        }
    }

    #[DataProvider('taxonomyTypes')]
    public function testStaleUpdateIdsNeverBecomeCreates(string $type): void
    {
        $marker = $this->taxonomyMarker("{$type}-stale-id");
        $originalName = $marker . '-deleted';
        $unexpectedName = $marker . '-unexpected';
        $staleId = $this->seedTaxonomy($type, $originalName);
        $this->deleteTaxonomyById($type, $staleId);

        try {
            $createOnlyController = $this->controller(
                ['shortLinkManager:createTaxonomy'],
                [$this->idParam($type) => $staleId, 'name' => $unexpectedName],
            );
            try {
                $this->save($createOnlyController, $type);
                self::fail('A stale update intent must not enter the create permission branch.');
            } catch (ForbiddenHttpException) {
                self::assertSame(['shortLinkManager:editTaxonomy'], $createOnlyController->requiredPermissions);
                self::assertSame(0, $this->countTaxonomyByName($type, $unexpectedName));
            }

            $controller = $this->controller(
                ['shortLinkManager:editTaxonomy'],
                [$this->idParam($type) => $staleId, 'name' => $unexpectedName],
            );
            $this->save($controller, $type);

            self::assertSame(['shortLinkManager:editTaxonomy'], $controller->requiredPermissions);
            self::assertSame(0, $this->countTaxonomyByName($type, $unexpectedName));
        } finally {
            $this->cleanupTaxonomyNames($type, [$originalName, $unexpectedName]);
        }
    }

    #[DataProvider('taxonomyTypes')]
    public function testInvalidInputCreatesNoRowsAndLeavesUpdatesUnchanged(string $type): void
    {
        $marker = $this->taxonomyMarker("{$type}-invalid-input");
        $existingName = $marker . '-existing';
        $existingId = $this->seedTaxonomy($type, $existingName);

        try {
            $createController = $this->controller(
                ['shortLinkManager:createTaxonomy'],
                ['name' => ''],
            );
            $this->save($createController, $type);
            self::assertSame(0, $this->countTaxonomyByName($type, ''));

            $updateController = $this->controller(
                ['shortLinkManager:editTaxonomy'],
                [$this->idParam($type) => $existingId, 'name' => ''],
            );
            $this->save($updateController, $type);
            self::assertSame($existingName, $this->taxonomyNameById($type, $existingId));
        } finally {
            $this->cleanupTaxonomyNames($type, [$existingName]);
        }
    }

    public function testAuthenticatedCpActionsUseDisposableUserPermissions(): void
    {
        $folderName = $this->taxonomyMarker('authenticated-folder-create');
        $folderUpdatedName = $this->taxonomyMarker('authenticated-folder-update');
        $tagName = $this->taxonomyMarker('authenticated-tag-create');
        $tagUpdatedName = $this->taxonomyMarker('authenticated-tag-update');
        $folderId = $this->seedTaxonomy('folder', $folderName);
        $tagId = $this->seedTaxonomy('tag', $tagName);
        $createUser = $this->createTestUser('sl_test_taxonomy_create_user_');
        $editUser = $this->createTestUser('sl_test_taxonomy_edit_user_');
        $this->grantPermissions($createUser, [
            'accessCp',
            'shortLinkManager:manageTaxonomy',
            'shortLinkManager:createTaxonomy',
        ]);
        $this->grantPermissions($editUser, [
            'accessCp',
            'shortLinkManager:manageTaxonomy',
            'shortLinkManager:editTaxonomy',
        ]);

        try {
            $this->actingAs($createUser);
            self::assertFalse(Craft::$app->getUser()->getIsGuest());
            self::assertTrue(Craft::$app->getUser()->checkPermission('accessCp'));
            self::assertTrue(Craft::$app->getUser()->checkPermission('shortLinkManager:createTaxonomy'));
            self::assertFalse(Craft::$app->getUser()->checkPermission('shortLinkManager:editTaxonomy'));

            $createdTagName = $tagName . '-created';
            $this->save($this->authenticatedController(['name' => $createdTagName]), 'tag');
            self::assertSame(1, $this->countTaxonomyByName('tag', $createdTagName));

            try {
                $this->save($this->authenticatedController([
                    'folderId' => $folderId,
                    'name' => $folderUpdatedName,
                ]), 'folder');
                self::fail('A create-only user must not update an existing folder.');
            } catch (ForbiddenHttpException) {
                self::assertSame($folderName, $this->taxonomyNameById('folder', $folderId));
            }

            $this->actingAs($editUser);
            self::assertFalse(Craft::$app->getUser()->getIsGuest());
            self::assertTrue(Craft::$app->getUser()->checkPermission('accessCp'));
            self::assertFalse(Craft::$app->getUser()->checkPermission('shortLinkManager:createTaxonomy'));
            self::assertTrue(Craft::$app->getUser()->checkPermission('shortLinkManager:editTaxonomy'));

            try {
                $this->save($this->authenticatedController(['name' => $folderName . '-created']), 'folder');
                self::fail('An edit-only user must not create a folder.');
            } catch (ForbiddenHttpException) {
                self::assertSame(0, $this->countTaxonomyByName('folder', $folderName . '-created'));
            }

            $this->save($this->authenticatedController([
                'tagId' => $tagId,
                'name' => $tagUpdatedName,
            ]), 'tag');
            self::assertSame($tagId, $this->taxonomyIdByName('tag', $tagUpdatedName));
        } finally {
            $this->cleanupTaxonomyNames('folder', [$folderName, $folderUpdatedName, $folderName . '-created']);
            $this->cleanupTaxonomyNames('tag', [$tagName, $tagUpdatedName, $tagName . '-created']);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function taxonomyTypes(): iterable
    {
        yield 'folder' => ['folder'];
        yield 'tag' => ['tag'];
    }

    /** @param list<string> $permissions @param array<string, mixed> $body */
    private function controller(array $permissions, array $body): PermissionAwareTaxonomyController
    {
        $this->installActionApplication($body);

        $controller = new PermissionAwareTaxonomyController('taxonomy', ShortLinkManager::$plugin);
        $controller->permissions = $permissions;

        return $controller;
    }

    /** @param array<string, mixed> $body */
    private function authenticatedController(array $body): TaxonomyController
    {
        $this->installActionApplication($body);

        return new AuthenticatedTaxonomyController('taxonomy', ShortLinkManager::$plugin);
    }

    /** @param array<string, mixed> $body */
    private function installActionApplication(array $body): void
    {
        if ($this->originalApplication === null) {
            $this->originalApplication = Craft::$app;
            $this->originalRequest = $this->originalApplication->get('request');
            $this->originalResponse = $this->originalApplication->get('response');
            $application = new TaxonomyActionApplication(
                $this->originalApplication,
                new TaxonomyActionSession(),
            );
            Craft::$app = $application;
            \Yii::$app = $application;
        }

        $this->originalApplication->set('request', new TaxonomyActionRequest($body));
        $this->originalApplication->set('response', new Response());
    }

    private function save(TaxonomyController $controller, string $type): Response
    {
        return $type === 'folder' ? $controller->actionSaveFolder() : $controller->actionSaveTag();
    }

    private function idParam(string $type): string
    {
        return $type === 'folder' ? 'folderId' : 'tagId';
    }

    private function table(string $type): string
    {
        return $type === 'folder' ? FolderRecord::tableName() : TagRecord::tableName();
    }

    private function otherTable(string $type): string
    {
        return $type === 'folder' ? TagRecord::tableName() : FolderRecord::tableName();
    }

    private function taxonomyMarker(string $suffix): string
    {
        return $this->nextTestMarker('sl_test_taxonomy_', $suffix);
    }

    private function seedTaxonomy(string $type, string $name): int
    {
        $taxonomy = ShortLinkManager::$plugin->taxonomy;
        $record = $type === 'folder' ? $taxonomy->createFolderRecord() : $taxonomy->createTagRecord();
        $saved = $type === 'folder'
            ? $taxonomy->saveFolder($record, $name)
            : $taxonomy->saveTag($record, $name);
        self::assertTrue($saved, json_encode($record->getErrors()));
        self::assertNotNull($record->id);

        return (int)$record->id;
    }

    /** @return array{links: int, otherTaxonomy: int, neighbor: array<string, mixed>|null, target: array<string, mixed>|null} */
    private function stateSnapshot(string $type, int $neighborId, ?int $targetId): array
    {
        return [
            'links' => (int)(new Query())->from('{{%shortlinkmanager}}')->count(),
            'otherTaxonomy' => (int)(new Query())->from($this->otherTable($type))->count(),
            'neighbor' => (new Query())->from($this->table($type))->where(['id' => $neighborId])->one() ?: null,
            'target' => $targetId === null
                ? null
                : ((new Query())->from($this->table($type))->where(['id' => $targetId])->one() ?: null),
        ];
    }

    private function countTaxonomyByName(string $type, string $name): int
    {
        return (int)(new Query())->from($this->table($type))->where(['name' => $name])->count();
    }

    private function taxonomyIdByName(string $type, string $name): ?int
    {
        $id = (new Query())->from($this->table($type))->where(['name' => $name])->select(['id'])->scalar();

        return $id === false ? null : (int)$id;
    }

    private function taxonomyNameById(string $type, int $id): ?string
    {
        $name = (new Query())->from($this->table($type))->where(['id' => $id])->select(['name'])->scalar();

        return $name === false ? null : (string)$name;
    }

    /** @param list<string> $names */
    private function cleanupTaxonomyNames(string $type, array $names): void
    {
        $ids = (new Query())->from($this->table($type))->where(['name' => $names])->select(['id'])->column();
        if ($ids !== []) {
            Db::delete($this->table($type), ['id' => array_map('intval', $ids)]);
        }
    }

    private function deleteTaxonomyById(string $type, int $id): void
    {
        Db::delete($this->table($type), ['id' => $id]);
    }
}

final class PermissionAwareTaxonomyController extends TaxonomyController
{
    /** @var list<string> */
    public array $permissions = [];

    /** @var list<string> */
    public array $requiredPermissions = [];

    public function requirePermission(string $permissionName): void
    {
        $this->requiredPermissions[] = $permissionName;
        if (!in_array(strtolower($permissionName), array_map('strtolower', $this->permissions), true)) {
            throw new ForbiddenHttpException();
        }
    }

    public function redirectToPostedUrl(?object $object = null, ?string $default = null): Response
    {
        return Craft::$app->getResponse();
    }
}

final class AuthenticatedTaxonomyController extends TaxonomyController
{
    public function redirectToPostedUrl(?object $object = null, ?string $default = null): Response
    {
        return Craft::$app->getResponse();
    }
}

final class TaxonomyActionRequest extends Request
{
    /** @param array<string, mixed> $body */
    public function __construct(private readonly array $body)
    {
        parent::__construct();
    }

    public function getBodyParams(): array
    {
        return $this->body;
    }

    public function getBodyParam($name, $defaultValue = null): mixed
    {
        return array_key_exists((string)$name, $this->body) ? $this->body[(string)$name] : $defaultValue;
    }

    public function getIsPost(): bool
    {
        return true;
    }
}

final class TaxonomyActionApplication extends ConsoleApplication
{
    public function __construct(
        private readonly ConsoleApplication $application,
        private readonly TaxonomyActionSession $session,
    ) {
        $this->edition = $application->edition;
    }

    public function getSession(): TaxonomyActionSession
    {
        return $this->session;
    }

    public function get($id, $throwException = true): ?object
    {
        if ($id === 'session') {
            return $this->session;
        }

        return $this->application->get($id, $throwException);
    }

    public function has($id, $checkInstance = false): bool
    {
        return $id === 'session' || $this->application->has($id, $checkInstance);
    }
}

final class TaxonomyActionSession extends Session
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function setNotice(string $message, array $settings = []): void
    {
        $this->values['notice'] = $message;
    }

    public function setError(string $message, array $settings = []): void
    {
        $this->values['error'] = $message;
    }

    public function getFlash($key, $defaultValue = null, $delete = false): mixed
    {
        $value = $this->values[(string)$key] ?? $defaultValue;
        if ($delete) {
            unset($this->values[(string)$key]);
        }

        return $value;
    }
}
