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
use craft\elements\User as UserElement;
use lindemannrock\shortlinkmanager\controllers\ShortlinksController;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.26.0
 */
#[CoversClass(ShortlinksController::class)]
#[CoversClass(ShortLink::class)]
final class ShortLinkPermissionGateTest extends TestCase
{
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
