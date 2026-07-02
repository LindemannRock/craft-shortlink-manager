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
use craft\console\User as ConsoleUser;
use craft\elements\User as UserElement;
use lindemannrock\shortlinkmanager\controllers\ImportExportController;
use lindemannrock\shortlinkmanager\controllers\ShortlinksController;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.26.0
 */
#[CoversClass(ShortlinksController::class)]
#[CoversClass(ImportExportController::class)]
#[CoversClass(ShortLink::class)]
final class ShortLinkPermissionGateTest extends TestCase
{
    private mixed $originalUser = null;

    protected function tearDown(): void
    {
        if ($this->originalUser !== null) {
            Craft::$app->set('user', $this->originalUser);
            $this->resetEditableSiteIds();
        }

        parent::tearDown();
    }

    public function testCustomBulkActionsUseEditablePluginSiteFilter(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/controllers/ShortlinksController.php');
        self::assertIsString($source);

        self::assertStringContainsString('ShortLinkManager::$plugin->getEnabledSites()', $source);
        self::assertStringContainsString('->siteId($siteIds)', $source);
        self::assertStringContainsString("requirePermission('shortLinkManager:createTaxonomy')", $source);
        self::assertStringNotContainsString("->from('{{%shortlinkmanager}}')\n            ->where(['id' => \$ids])", $source);
    }

    public function testImportExportUsesEditablePluginSiteFilter(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/controllers/ImportExportController.php');
        self::assertIsString($source);

        self::assertStringContainsString('ShortLinkManager::$plugin->getEnabledSites()', $source);
        self::assertStringContainsString('->siteId($siteIds)', $source);
        self::assertStringNotContainsString("ShortLink::find()->site('*')", $source);
        self::assertStringContainsString('in_array((int)$s->id, $siteIds, true)', $source);
    }

    public function testImportWriteGateRejectsUneditableSites(): void
    {
        $sites = Craft::$app->getSites()->getAllSites();
        if (count($sites) < 2) {
            self::markTestSkipped('Import write-scope regression requires at least two Craft sites.');
        }

        $editableSite = $sites[0];
        $blockedSite = $sites[1];

        $this->withSettings(['enabledSites' => [(int)$editableSite->id, (int)$blockedSite->id]], function() use ($editableSite, $blockedSite): void {
            $this->withSessionPermissions([
                'shortLinkManager:importLinks',
                'editSite:' . $editableSite->uid,
            ], function() use ($editableSite, $blockedSite): void {
                self::assertTrue($this->canImportToSite((int)$editableSite->id));
                self::assertFalse($this->canImportToSite((int)$blockedSite->id));
            });
        });
    }

    public function testDeleteFailsClosedWhenElementSiteIsMissing(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/controllers/ShortlinksController.php');
        self::assertIsString($source);

        self::assertStringContainsString("throw new \\yii\\web\\BadRequestHttpException('Invalid site.');", $source);
        self::assertStringContainsString("\$this->requirePermission('editSite:' . \$site->uid);", $source);
        self::assertStringNotContainsString("if (\$site) {\n                \$this->requirePermission('editSite:' . \$site->uid);", $source);
    }

    public function testNativeElementActionsRequireEditableSiteForExistingLinks(): void
    {
        $sites = Craft::$app->getSites()->getAllSites();
        if (count($sites) < 2) {
            self::markTestSkipped('Element action site-scope regression requires at least two Craft sites.');
        }

        $editableSite = $sites[0];
        $blockedSite = $sites[1];

        $user = new PermissionGateUserElement([
            'shortLinkManager:createLinks',
            'shortLinkManager:editLinks',
            'shortLinkManager:deleteLinks',
            'editSite:' . $editableSite->uid,
        ]);

        $editableLink = new ShortLink();
        $editableLink->id = 1001;
        $editableLink->siteId = (int)$editableSite->id;

        self::assertTrue($editableLink->canSave($user));
        self::assertTrue($editableLink->canDelete($user));
        self::assertTrue($editableLink->canDuplicate($user));

        $blockedLink = new ShortLink();
        $blockedLink->id = 1002;
        $blockedLink->siteId = (int)$blockedSite->id;

        self::assertFalse($blockedLink->canSave($user));
        self::assertFalse($blockedLink->canDelete($user));
        self::assertFalse($blockedLink->canDuplicate($user));
    }

    private function canImportToSite(int $siteId): bool
    {
        $controller = new ImportExportController('import-export', ShortLinkManager::getInstance());
        $method = new \ReflectionMethod($controller, 'canImportToSite');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $siteId);
        self::assertIsBool($result);

        return $result;
    }

    /**
     * @param string[] $permissions
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withSessionPermissions(array $permissions, callable $callback): mixed
    {
        if ($this->originalUser === null) {
            $this->originalUser = Craft::$app->getUser();
        }

        Craft::$app->set('user', new PermissionGateUserSession($permissions));
        $this->resetEditableSiteIds();

        try {
            return $callback();
        } finally {
            Craft::$app->set('user', $this->originalUser);
            $this->resetEditableSiteIds();
            $this->originalUser = null;
        }
    }

    private function resetEditableSiteIds(): void
    {
        $property = new \ReflectionProperty(Craft::$app->getSites(), '_editableSiteIds');
        $property->setAccessible(true);
        $property->setValue(Craft::$app->getSites(), null);
    }
}

final class PermissionGateUserSession extends ConsoleUser
{
    /**
     * @param string[] $permissions
     */
    public function __construct(private readonly array $permissions)
    {
        parent::__construct();
    }

    public function checkPermission(string $permissionName): bool
    {
        return in_array(strtolower($permissionName), array_map('strtolower', $this->permissions), true);
    }

    public function getId(): ?int
    {
        return 1;
    }

    public function getIsGuest(): bool
    {
        return false;
    }
}

final class PermissionGateUserElement extends UserElement
{
    /**
     * @param string[] $permissions
     */
    public function __construct(private readonly array $permissions)
    {
        parent::__construct();
    }

    public function can(string $permission): bool
    {
        return in_array(strtolower($permission), array_map('strtolower', $this->permissions), true);
    }
}
