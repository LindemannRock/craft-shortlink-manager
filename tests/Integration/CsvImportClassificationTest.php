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
use craft\console\User as ConsoleUser;
use craft\db\Query;
use craft\helpers\Db;
use craft\web\Request;
use craft\web\Session;
use lindemannrock\base\helpers\SlugHandleHelper;
use lindemannrock\shortlinkmanager\controllers\ImportExportController;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use yii\web\Response;

#[CoversClass(ImportExportController::class)]
final class CsvImportClassificationTest extends TestCase
{
    /** @var list<string> */
    private array $ownedCodes = [];

    /** @var list<string> */
    private array $ownedFilenames = [];

    protected function tearDown(): void
    {
        try {
            $this->cleanupOwnedImports();
        } finally {
            parent::tearDown();
        }
    }

    public function testSiteHandleIsAuthoritativeAcrossMappingOrdersAndSelectorForms(): void
    {
        [$handleSite, $numericSite] = $this->twoSites();
        $permissions = [
            'shortLinkManager:importLinks',
            'editSite:' . $handleSite->uid,
            'editSite:' . $numericSite->uid,
        ];
        $cases = [
            'handle-before-id' => [
                [0 => 'code', 1 => 'destinationUrl', 2 => 'siteHandle', 3 => 'siteId'],
                [$handleSite->handle, (string)$numericSite->id],
                [$numericSite->handle, (string)$numericSite->id],
                (int)$handleSite->id,
                (int)$numericSite->id,
            ],
            'handle-after-id' => [
                [0 => 'code', 1 => 'destinationUrl', 2 => 'siteId', 3 => 'siteHandle'],
                [(string)$numericSite->id, $handleSite->handle],
                [(string)$numericSite->id, $numericSite->handle],
                (int)$handleSite->id,
                (int)$numericSite->id,
            ],
            'handle-only' => [
                [0 => 'code', 1 => 'destinationUrl', 2 => 'siteHandle'],
                [$handleSite->handle],
                [$numericSite->handle],
                (int)$handleSite->id,
                (int)$numericSite->id,
            ],
            'id-only' => [
                [0 => 'code', 1 => 'destinationUrl', 2 => 'siteId'],
                [(string)$numericSite->id],
                [(string)$handleSite->id],
                (int)$numericSite->id,
                (int)$handleSite->id,
            ],
            'blank-handle-plus-id' => [
                [0 => 'code', 1 => 'destinationUrl', 2 => 'siteHandle', 3 => 'siteId'],
                ['', (string)$numericSite->id],
                ['', (string)$handleSite->id],
                (int)$numericSite->id,
                (int)$handleSite->id,
            ],
            'matching-handle-and-id' => [
                [0 => 'code', 1 => 'destinationUrl', 2 => 'siteHandle', 3 => 'siteId'],
                [$handleSite->handle, (string)$handleSite->id],
                [$numericSite->handle, (string)$numericSite->id],
                (int)$handleSite->id,
                (int)$numericSite->id,
            ],
            'neither-selector' => [
                [0 => 'code', 1 => 'destinationUrl'],
                [],
                null,
                (int)Craft::$app->getSites()->getCurrentSite()->id,
                null,
            ],
        ];

        $this->withSettings([
            'enabledSites' => [(int)$handleSite->id, (int)$numericSite->id],
        ], function() use ($cases, $permissions): void {
            foreach ($cases as $case => [$mapping, $selectorValues, $neighborSelectorValues, $expectedSiteId, $neighborSiteId]) {
                $code = $this->importCode("selector-{$case}");
                $filename = $this->importFilename("selector-{$case}");
                $destination = "https://example.com/{$code}";
                $rows = [[$code, $destination, ...$selectorValues]];
                if (is_array($neighborSelectorValues)) {
                    $rows[] = [$code, $destination . '-neighbor', ...$neighborSelectorValues];
                }

                $result = $this->previewAndImport($mapping, $rows, $filename, $permissions);
                self::assertSame(count($rows), $result['preview']['summary']['validRows'] ?? null, $case);
                self::assertSame(0, $result['preview']['summary']['errors'] ?? null, $case);
                self::assertSame($expectedSiteId, $result['preview']['validRows'][0]['resolvedSiteId'] ?? null, $case);

                $linkId = $this->linkIdByCode($code);
                self::assertNotNull($linkId, $case);
                $expectedVariant = ShortLink::find()->id($linkId)->siteId($expectedSiteId)->status(null)->one();
                self::assertInstanceOf(ShortLink::class, $expectedVariant, $case);
                self::assertSame($destination, $this->contentDestination($linkId, $expectedSiteId), $case);

                if ($neighborSiteId !== null) {
                    $neighborVariant = ShortLink::find()->id($linkId)->siteId($neighborSiteId)->status(null)->one();
                    self::assertInstanceOf(ShortLink::class, $neighborVariant, $case);
                    self::assertSame($destination . '-neighbor', $this->contentDestination($linkId, $neighborSiteId), $case);
                }
                self::assertSame(1, $this->countMainRowsByCode($code), $case);
                self::assertSame(count($rows), $this->historyValue($filename, 'imported'), $case);
                self::assertSame(0, $this->historyValue($filename, 'failed'), $case);
            }
        });
    }

    public function testInvalidNonblankHandleDoesNotFallBackToNumericId(): void
    {
        [$handleSite, $numericSite] = $this->twoSites();
        $invalidHandle = 'slTestMissingSite' . bin2hex(random_bytes(4));
        $cases = [
            'handle-before-id' => [
                [0 => 'code', 1 => 'destinationUrl', 2 => 'siteHandle', 3 => 'siteId'],
                [$invalidHandle, (string)$numericSite->id],
            ],
            'handle-after-id' => [
                [0 => 'code', 1 => 'destinationUrl', 2 => 'siteId', 3 => 'siteHandle'],
                [(string)$numericSite->id, $invalidHandle],
            ],
        ];

        $this->withSettings([
            'enabledSites' => [(int)$handleSite->id, (int)$numericSite->id],
        ], function() use ($handleSite, $numericSite, $cases): void {
            foreach ($cases as $case => [$mapping, $selectorValues]) {
                $code = $this->importCode("invalid-handle-{$case}");
                $filename = $this->importFilename("invalid-handle-{$case}");
                $result = $this->previewAndImport(
                    $mapping,
                    [[$code, "https://example.com/{$code}", ...$selectorValues]],
                    $filename,
                    [
                        'shortLinkManager:importLinks',
                        'editSite:' . $handleSite->uid,
                        'editSite:' . $numericSite->uid,
                    ],
                );

                self::assertSame([
                    'totalRows' => 1,
                    'validRows' => 0,
                    'duplicates' => 0,
                    'errors' => 1,
                ], $result['preview']['summary'] ?? null, $case);
                self::assertSame([], $result['preview']['validRows'] ?? null, $case);
                self::assertSame($code, $result['preview']['errorRows'][0]['code'] ?? null, $case);
                self::assertStringContainsString('Site Handle', (string)($result['preview']['errorRows'][0]['error'] ?? ''), $case);
                self::assertSame(0, $this->countMainRowsByCode($code), $case);
            }
        });
    }

    public function testDisabledUneditableAndUnknownSitesLeaveNoPersistedRows(): void
    {
        [$enabledSite, $deniedSite] = $this->twoSites();
        $unknownSiteId = max(Craft::$app->getSites()->getAllSiteIds()) + 1000000;
        $cases = [
            'plugin-disabled' => [
                [0 => 'code', 1 => 'destinationUrl', 2 => 'siteHandle'],
                [$deniedSite->handle],
                [(int)$enabledSite->id],
                ['shortLinkManager:importLinks', 'editSite:' . $enabledSite->uid, 'editSite:' . $deniedSite->uid],
                (int)$deniedSite->id,
            ],
            'uneditable' => [
                [0 => 'code', 1 => 'destinationUrl', 2 => 'siteHandle'],
                [$deniedSite->handle],
                [(int)$enabledSite->id, (int)$deniedSite->id],
                ['shortLinkManager:importLinks', 'editSite:' . $enabledSite->uid],
                (int)$deniedSite->id,
            ],
            'unknown-id' => [
                [0 => 'code', 1 => 'destinationUrl', 2 => 'siteId'],
                [(string)$unknownSiteId],
                [(int)$enabledSite->id, (int)$deniedSite->id],
                ['shortLinkManager:importLinks', 'editSite:' . $enabledSite->uid, 'editSite:' . $deniedSite->uid],
                $unknownSiteId,
            ],
        ];

        foreach ($cases as $case => [$mapping, $selectorValues, $enabledSiteIds, $permissions, $resolvedSiteId]) {
            $code = $this->importCode("denied-{$case}");
            $filename = $this->importFilename("denied-{$case}");
            $this->withSettings(['enabledSites' => $enabledSiteIds], function() use (
                $mapping,
                $selectorValues,
                $permissions,
                $resolvedSiteId,
                $code,
                $filename,
                $case,
            ): void {
                $result = $this->previewAndImport(
                    $mapping,
                    [[$code, "https://example.com/{$code}", ...$selectorValues]],
                    $filename,
                    $permissions,
                );

                self::assertSame(1, $result['preview']['summary']['validRows'] ?? null, $case);
                self::assertSame($resolvedSiteId, $result['preview']['validRows'][0]['resolvedSiteId'] ?? null, $case);
                self::assertSame(0, $this->countMainRowsByCode($code), $case);
                self::assertSame(0, $this->historyValue($filename, 'imported'), $case);
                self::assertSame(1, $this->historyValue($filename, 'failed'), $case);
            });
        }
    }

    public function testPreviewRejectsFinalInvalidRowsAndImportsValidNeighbors(): void
    {
        [$site] = $this->twoSites();
        $filename = $this->importFilename('validation');
        $invalidCode = '***';
        $invalidStatusCode = $this->importCode('status-999');
        $allowed = [
            $this->importCode('status-301') => 301,
            $this->importCode('status-302') => 302,
            $this->importCode('status-307') => 307,
            str_replace('-', ' ', $this->importCode('normalizable-308')) => 308,
        ];
        $rows = [
            [$invalidCode, 'https://example.com/invalid-code', '302'],
            [$invalidStatusCode, 'https://example.com/invalid-status', '999'],
        ];
        foreach ($allowed as $code => $status) {
            $rows[] = [$code, 'https://example.com/' . rawurlencode($code), (string)$status];
        }

        $folderCount = (int)(new Query())->from('{{%shortlinkmanager_folders}}')->count();
        $tagCount = (int)(new Query())->from('{{%shortlinkmanager_tags}}')->count();

        $ownedLinkIds = [];
        $this->withSettings(['enabledSites' => [(int)$site->id]], function() use (
            $site,
            $filename,
            $invalidCode,
            $invalidStatusCode,
            $allowed,
            $rows,
            $folderCount,
            $tagCount,
            &$ownedLinkIds,
        ): void {
            $result = $this->previewAndImport(
                [0 => 'code', 1 => 'destinationUrl', 2 => 'httpCode'],
                $rows,
                $filename,
                ['shortLinkManager:importLinks', 'editSite:' . $site->uid],
            );

            self::assertSame([
                'totalRows' => 6,
                'validRows' => 4,
                'duplicates' => 0,
                'errors' => 2,
            ], $result['preview']['summary'] ?? null);
            $validCodes = array_column($result['preview']['validRows'] ?? [], 'code');
            self::assertNotContains($invalidCode, $validCodes);
            self::assertNotContains($invalidStatusCode, $validCodes);
            self::assertSame([$invalidCode, $invalidStatusCode], array_column($result['preview']['errorRows'] ?? [], 'code'));
            self::assertStringContainsString('Code', (string)($result['preview']['errorRows'][0]['error'] ?? ''));
            self::assertStringContainsString('Code is invalid.', (string)($result['preview']['errorRows'][1]['error'] ?? ''));

            self::assertSame(0, $this->countMainRowsByCode($invalidCode));
            self::assertSame(0, $this->countMainRowsByCode($invalidStatusCode));
            foreach ($allowed as $code => $status) {
                $linkId = $this->linkIdByCode($code);
                self::assertNotNull($linkId, $code);
                $ownedLinkIds[] = $linkId;
                $variant = ShortLink::find()->id($linkId)->siteId((int)$site->id)->status(null)->one();
                self::assertInstanceOf(ShortLink::class, $variant, $code);
                self::assertSame($status, $variant->httpCode, $code);
                self::assertNotSame('', (string)$variant->slug, $code);
            }

            self::assertSame(4, $this->historyValue($filename, 'imported'));
            self::assertSame(0, $this->historyValue($filename, 'failed'));
            self::assertSame($folderCount, (int)(new Query())->from('{{%shortlinkmanager_folders}}')->count());
            self::assertSame($tagCount, (int)(new Query())->from('{{%shortlinkmanager_tags}}')->count());
        });

        $this->cleanupOwnedImports();
        self::assertSame(0, $this->historyCount($filename));
        self::assertSame(0, $this->countMainRowsByCode($invalidCode));
        self::assertSame(0, $this->countMainRowsByCode($invalidStatusCode));
        foreach (array_keys($allowed) as $code) {
            self::assertSame(0, $this->countMainRowsByCode($code));
        }
        foreach ($ownedLinkIds as $linkId) {
            self::assertSame(0, (int)(new Query())->from('{{%shortlinkmanager}}')->where(['id' => $linkId])->count());
            self::assertSame(0, (int)(new Query())->from('{{%shortlinkmanager_content}}')->where(['shortLinkId' => $linkId])->count());
            self::assertSame(0, (int)(new Query())->from('{{%elements_sites}}')->where(['elementId' => $linkId])->count());
            self::assertSame(0, (int)(new Query())->from('{{%elements}}')->where(['id' => $linkId])->count());
        }
    }

    /**
     * @param array<int, string> $mapping
     * @param list<list<string>> $rows
     * @param list<string> $permissions
     * @return array{preview: array<string, mixed>}
     */
    private function previewAndImport(array $mapping, array $rows, string $filename, array $permissions): array
    {
        $importData = [
            'headers' => [],
            'allRows' => $rows,
            'rowCount' => count($rows),
            'filename' => $filename,
            'filesize' => 123,
        ];

        return $this->withImportApplication($mapping, $importData, $permissions, function(): array {
            $controller = new CapturingCsvImportController('import-export', ShortLinkManager::$plugin);
            $controller->actionPreview();
            $preview = $controller->templateVariables;
            $controller->actionImport();

            return ['preview' => $preview];
        });
    }

    /**
     * @param array<int, string> $mapping
     * @param array<string, mixed> $importData
     * @param list<string> $permissions
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withImportApplication(array $mapping, array $importData, array $permissions, callable $callback): mixed
    {
        $originalApplication = Craft::$app;
        $originalRequest = $originalApplication->get('request');
        $originalResponse = $originalApplication->get('response');
        $session = new CsvImportSession();
        $application = new CsvImportApplication(
            $originalApplication,
            $session,
            new CsvImportUser($permissions),
        );
        $originalApplication->set('request', new CsvImportRequest(['mapping' => $mapping]));
        $originalApplication->set('response', new Response());
        $session->set('shortlink-import', $importData);
        Craft::$app = $application;
        \Yii::$app = $application;

        try {
            return $callback();
        } finally {
            Craft::$app = $originalApplication;
            \Yii::$app = $originalApplication;
            $originalApplication->set('request', $originalRequest);
            $originalApplication->set('response', $originalResponse);
        }
    }

    /** @return array{\craft\models\Site, \craft\models\Site} */
    private function twoSites(): array
    {
        $sites = Craft::$app->getSites()->getAllSites();
        self::assertGreaterThanOrEqual(2, count($sites), 'CSV multisite behavior requires the disposable fixture site set.');
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        foreach ($sites as $site) {
            if ((int)$site->id !== (int)$currentSite->id) {
                return [$currentSite, $site];
            }
        }

        self::fail('CSV multisite behavior requires a site distinct from the current site.');
    }

    private function importCode(string $suffix): string
    {
        $code = str_replace('_', '-', $this->nextTestMarker(self::MARKER, "csv-{$suffix}"));
        $this->ownedCodes[] = $code;

        return $code;
    }

    private function importFilename(string $suffix): string
    {
        $filename = str_replace('_', '-', $this->nextTestMarker(self::MARKER, "csv-{$suffix}")) . '.csv';
        $this->ownedFilenames[] = $filename;

        return $filename;
    }

    private function linkIdByCode(string $code): ?int
    {
        $slug = SlugHandleHelper::normalizeSlug($code, '');
        $condition = $slug === ''
            ? ['code' => $code]
            : ['or', ['code' => $code], ['slug' => $slug]];
        $id = (new Query())->from('{{%shortlinkmanager}}')->where($condition)->select(['id'])->scalar();

        return $id === false ? null : (int)$id;
    }

    private function countMainRowsByCode(string $code): int
    {
        return (int)(new Query())->from('{{%shortlinkmanager}}')->where(['code' => $code])->count();
    }

    private function contentDestination(int $linkId, int $siteId): ?string
    {
        $destination = (new Query())
            ->from('{{%shortlinkmanager_content}}')
            ->where(['shortLinkId' => $linkId, 'siteId' => $siteId])
            ->select(['destinationUrl'])
            ->scalar();

        return $destination === false ? null : (string)$destination;
    }

    private function historyCount(string $filename): int
    {
        return (int)(new Query())->from('{{%shortlinkmanager_import_history}}')->where(['filename' => $filename])->count();
    }

    private function historyValue(string $filename, string $attribute): int
    {
        $value = (new Query())
            ->from('{{%shortlinkmanager_import_history}}')
            ->where(['filename' => $filename])
            ->select([$attribute])
            ->scalar();
        self::assertNotFalse($value, "Import history {$attribute} must exist for {$filename}.");

        return (int)$value;
    }

    private function cleanupOwnedImports(): void
    {
        if ($this->ownedCodes !== []) {
            $rows = (new Query())
                ->from('{{%shortlinkmanager}}')
                ->where(['code' => $this->ownedCodes])
                ->select(['id', 'slug'])
                ->all();
            foreach ($rows as $row) {
                $element = ShortLink::find()->id((int)$row['id'])->status(null)->trashed(null)->one();
                if ($element !== null) {
                    Craft::$app->getElements()->deleteElement($element, true);
                }
                $this->shortLinks->invalidateShortLinkCache((int)$row['id'], (string)$row['slug']);
            }
        }

        if ($this->ownedFilenames !== []) {
            Db::delete('{{%shortlinkmanager_import_history}}', ['filename' => $this->ownedFilenames]);
        }

        $this->ownedCodes = [];
        $this->ownedFilenames = [];
    }
}

final class CapturingCsvImportController extends ImportExportController
{
    /** @var array<string, mixed> */
    public array $templateVariables = [];

    /** @param array<string, mixed> $variables */
    public function renderTemplate(string $template, array $variables = [], ?string $templateMode = null): Response
    {
        $this->templateVariables = $variables;

        return Craft::$app->getResponse();
    }
}

final class CsvImportRequest extends Request
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
        return $this->body[(string)$name] ?? $defaultValue;
    }

    public function getIsPost(): bool
    {
        return true;
    }
}

final class CsvImportApplication extends ConsoleApplication
{
    public function __construct(
        private readonly ConsoleApplication $application,
        private readonly CsvImportSession $session,
        private readonly CsvImportUser $user,
    ) {
    }

    public function getSession(): CsvImportSession
    {
        return $this->session;
    }

    public function get($id, $throwException = true): ?object
    {
        if ($id === 'session') {
            return $this->session;
        }
        if ($id === 'user') {
            return $this->user;
        }

        return $this->application->get($id, $throwException);
    }

    public function has($id, $checkInstance = false): bool
    {
        return in_array($id, ['session', 'user'], true) || $this->application->has($id, $checkInstance);
    }
}

final class CsvImportUser extends ConsoleUser
{
    /** @param list<string> $permissions */
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

final class CsvImportSession extends Session
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function has($key): bool
    {
        return array_key_exists((string)$key, $this->values);
    }

    public function get($key, $defaultValue = null): mixed
    {
        return $this->values[(string)$key] ?? $defaultValue;
    }

    public function set($key, $value): void
    {
        $this->values[(string)$key] = $value;
    }

    public function remove($key): mixed
    {
        $key = (string)$key;
        $value = $this->values[$key] ?? null;
        unset($this->values[$key]);

        return $value;
    }

    public function setNotice(string $message, array $settings = []): void
    {
        $this->set('notice', $message);
    }

    public function setError(string $message, array $settings = []): void
    {
        $this->set('error', $message);
    }
}
