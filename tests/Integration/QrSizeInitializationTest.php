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
use craft\base\Element;
use craft\console\Application as ConsoleApplication;
use craft\console\User as ConsoleUser;
use craft\db\Query;
use craft\helpers\Db;
use craft\web\Request;
use craft\web\Response;
use craft\web\Session;
use lindemannrock\shortlinkmanager\controllers\ImportExportController;
use lindemannrock\shortlinkmanager\controllers\QrCodeController;
use lindemannrock\shortlinkmanager\controllers\ShortlinksController;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\fields\ShortLinkField;
use lindemannrock\shortlinkmanager\services\QrCodeService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use lindemannrock\shortlinkmanager\variables\ShortLinkManagerVariable;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pins configured QR-size initialization across supported new-link producers.
 *
 * @since 5.28.4
 */
final class QrSizeInitializationTest extends TestCase
{
    private ?object $originalRequest = null;
    private ?object $originalResponse = null;

    protected function tearDown(): void
    {
        if ($this->originalRequest !== null) {
            Craft::$app->set('request', $this->originalRequest);
            $this->originalRequest = null;
        }
        if ($this->originalResponse !== null) {
            Craft::$app->set('response', $this->originalResponse);
            $this->originalResponse = null;
        }
        parent::tearDown();
    }

    /** @return array<string, array{int}> */
    public static function configuredSizes(): array
    {
        return [
            'lower boundary' => [100],
            'standard default' => [256],
            'upper boundary' => [1000],
        ];
    }

    #[DataProvider('configuredSizes')]
    public function testNewControlPanelFormUsesConfiguredSize(int $configuredSize): void
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $this->installRequest();

        $this->withSettings([
            'defaultQrSize' => $configuredSize,
            'enabledSites' => [$siteId],
        ], function() use ($configuredSize): void {
            $controller = new CapturingShortlinksController('shortlinks', ShortLinkManager::$plugin);
            $controller->actionEdit();

            $shortLink = $controller->templateVariables['shortLink'] ?? null;
            self::assertInstanceOf(ShortLink::class, $shortLink);
            self::assertSame($configuredSize, $shortLink->qrCodeSize);
        });
    }

    public function testNewControlPanelSaveUsesConfiguredSizeWhenOmitted(): void
    {
        $code = $this->testCode('cp-omitted');
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $this->installRequest($this->controlPanelBody($code));

        $this->withSettings([
            'defaultQrSize' => 1000,
            'enabledSites' => [$siteId],
        ], function() use ($code): void {
            $controller = new CapturingShortlinksController('shortlinks', ShortLinkManager::$plugin);
            $controller->actionSave();

            $shortLink = $this->shortLinks->getByCode($code);
            self::assertInstanceOf(ShortLink::class, $shortLink);
            $this->trackShortLinkForCleanup($shortLink);
            self::assertSame(1000, $shortLink->qrCodeSize);
        });
    }

    public function testExplicitControlPanelSizeWins(): void
    {
        $code = $this->testCode('cp-explicit');
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $this->installRequest($this->controlPanelBody($code, ['qrCodeSize' => 640]));

        $this->withSettings([
            'defaultQrSize' => 1000,
            'enabledSites' => [$siteId],
        ], function() use ($code): void {
            $controller = new CapturingShortlinksController('shortlinks', ShortLinkManager::$plugin);
            $controller->actionSave();

            $shortLink = $this->shortLinks->getByCode($code);
            self::assertInstanceOf(ShortLink::class, $shortLink);
            $this->trackShortLinkForCleanup($shortLink);
            self::assertSame(640, $shortLink->qrCodeSize);
        });
    }

    public function testExistingControlPanelSavePreservesSizeWhenOmitted(): void
    {
        $shortLink = $this->seedShortLink(['qrCodeSize' => 375]);
        $shortLink->qrCodeSize = 375;
        self::assertTrue($this->shortLinks->saveShortLink($shortLink));

        $this->installRequest($this->controlPanelBody((string)$shortLink->code, [
            'linkId' => $shortLink->id,
            'siteId' => $shortLink->siteId,
        ]));

        $this->withSettings([
            'defaultQrSize' => 1000,
            'enabledSites' => [$shortLink->siteId],
        ], function() use ($shortLink): void {
            $controller = new CapturingShortlinksController('shortlinks', ShortLinkManager::$plugin);
            $controller->actionSave();

            $reloaded = ShortLink::find()
                ->id($shortLink->id)
                ->siteId($shortLink->siteId)
                ->status(null)
                ->one();

            self::assertInstanceOf(ShortLink::class, $reloaded);
            self::assertSame(375, $reloaded->qrCodeSize);
        });
    }

    public function testServiceCreationUsesDefaultOnlyWhenSizeIsOmitted(): void
    {
        $this->withSettings(['defaultQrSize' => 1000], function(): void {
            $inherited = $this->shortLinks->createShortLink($this->serviceOptions('service-default'));
            $explicit = $this->shortLinks->createShortLink($this->serviceOptions('service-explicit', [
                'qrCodeSize' => 420,
            ]));

            self::assertInstanceOf(ShortLink::class, $inherited);
            self::assertInstanceOf(ShortLink::class, $explicit);
            $this->trackShortLinkForCleanup($inherited);
            $this->trackShortLinkForCleanup($explicit);
            self::assertSame(1000, $inherited->qrCodeSize);
            self::assertSame(420, $explicit->qrCodeSize);
        });
    }

    public function testTwigCreationUsesConfiguredSize(): void
    {
        $this->withSettings(['defaultQrSize' => 1000], function(): void {
            $shortLink = (new ShortLinkManagerVariable())->create($this->serviceOptions('twig-default'));

            self::assertInstanceOf(ShortLink::class, $shortLink);
            $this->trackShortLinkForCleanup($shortLink);
            self::assertSame(1000, $shortLink->qrCodeSize);
        });
    }

    public function testShortLinkFieldCreationUsesConfiguredSize(): void
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $code = $this->testCode('field-default');
        $target = $this->seedShortLink([
            'code' => $code . '-target',
            'slug' => $code . '-target',
        ]);
        $element = new QrSizeFieldElement();
        $element->id = $target->id;
        $element->siteId = $siteId;
        $element->fieldValue = $code;
        $element->url = 'https://example.com/field-default';
        $field = new ShortLinkField([
            'handle' => 'shortlinkField',
            'linkType' => 'vanity',
        ]);

        $this->withSettings([
            'defaultQrSize' => 1000,
            'enabledSites' => [$siteId],
        ], function() use ($field, $element): void {
            $field->afterElementSave($element, true);

            $shortLink = $this->shortLinks->getByElement($element, $element->siteId);
            self::assertInstanceOf(ShortLink::class, $shortLink);
            $this->trackShortLinkForCleanup($shortLink);
            self::assertSame(1000, $shortLink->qrCodeSize);
        });
    }

    public function testCsvPreviewNormalizesMissingBlankAndExplicitSizes(): void
    {
        $fixture = $this->csvSizeFixture('preview');
        $this->installRequest(['mapping' => [
            0 => 'code',
            1 => 'destinationUrl',
            2 => 'qrCodeSize',
        ]]);

        $this->withSettings([
            'defaultQrSize' => 1000,
            'enabledSites' => [Craft::$app->getSites()->getCurrentSite()->id],
        ], function() use ($fixture): void {
            $this->withImportSession($fixture['importData'], function() use ($fixture): void {
                $controller = new TestImportExportController('import-export', ShortLinkManager::$plugin);
                $controller->actionPreview();

                $actual = [];
                foreach ($controller->templateVariables['validRows'] ?? [] as $row) {
                    $actual[(string)$row['code']] = (int)$row['qrCodeSize'];
                }

                self::assertSame($fixture['expectedSizes'], $actual);
                self::assertSame([
                    'totalRows' => 6,
                    'validRows' => 6,
                    'duplicates' => 0,
                    'errors' => 0,
                ], $controller->templateVariables['summary'] ?? null);
            });
        });
    }

    public function testCsvImportPersistsPreviewedMissingBlankAndExplicitSizes(): void
    {
        $fixture = $this->csvSizeFixture('import');
        $filename = $this->testCode('import-file') . '.csv';
        $fixture['importData']['filename'] = $filename;
        $fixture['importData']['filesize'] = 321;
        $this->installRequest(['mapping' => [
            0 => 'code',
            1 => 'destinationUrl',
            2 => 'qrCodeSize',
        ]]);

        try {
            $this->withSettings([
                'defaultQrSize' => 1000,
                'enabledSites' => [Craft::$app->getSites()->getCurrentSite()->id],
            ], function() use ($fixture): void {
                $this->withImportSession($fixture['importData'], function() use ($fixture): void {
                    $controller = new TestImportExportController('import-export', ShortLinkManager::$plugin);
                    $controller->actionPreview();
                    $controller->actionImport();

                    $persistedRows = (new Query())
                        ->select(['code', 'qrCodeSize'])
                        ->from('{{%shortlinkmanager}}')
                        ->where(['code' => array_keys($fixture['expectedSizes'])])
                        ->all();
                    $actual = [];
                    foreach ($persistedRows as $row) {
                        $actual[(string)$row['code']] = (int)$row['qrCodeSize'];
                    }

                    $expected = $fixture['expectedSizes'];
                    ksort($expected);
                    ksort($actual);
                    self::assertSame($expected, $actual);
                });
            });

            $history = (new Query())
                ->from('{{%shortlinkmanager_import_history}}')
                ->where(['filename' => $filename])
                ->one();
            self::assertIsArray($history);
            self::assertSame(6, (int)$history['imported']);
            self::assertSame(0, (int)$history['failed']);
        } finally {
            $this->cleanupImportedFixture(array_keys($fixture['expectedSizes']), $filename);
        }
    }

    /**
     * @return array{
     *   importData: array{headers: list<string>, allRows: list<list<string>>, rowCount: int},
     *   expectedSizes: array<string, int>
     * }
     */
    private function csvSizeFixture(string $suffix): array
    {
        $cases = [
            'missing' => [false, ''],
            'blank' => [true, ''],
            'zero' => [true, '0'],
            'valid' => [true, '640'],
            'lower-bound' => [true, '50'],
            'upper-bound' => [true, '2000'],
        ];
        $expectedByCase = [
            'missing' => 1000,
            'blank' => 1000,
            'zero' => 100,
            'valid' => 640,
            'lower-bound' => 100,
            'upper-bound' => 1000,
        ];
        $rows = [];
        $expectedSizes = [];

        foreach ($cases as $case => [$hasSizeColumn, $size]) {
            $code = $this->testCode("csv-{$suffix}-{$case}");
            $row = [$code, "https://example.com/{$code}"];
            if ($hasSizeColumn) {
                $row[] = $size;
            }
            $rows[] = $row;
            $expectedSizes[$code] = $expectedByCase[$case];
        }

        return [
            'importData' => [
                'headers' => ['code', 'destinationUrl', 'qrCodeSize'],
                'allRows' => $rows,
                'rowCount' => count($rows),
            ],
            'expectedSizes' => $expectedSizes,
        ];
    }

    /**
     * @param array<string, mixed> $importData
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withImportSession(array $importData, callable $callback): mixed
    {
        $originalApplication = Craft::$app;
        $session = new QrSizeSession();
        $application = new QrSizeApplication($originalApplication, $session, new QrSizeImportUser());
        Craft::$app = $application;
        \Yii::$app = $application;
        $session->set('shortlink-import', $importData);

        try {
            return $callback();
        } finally {
            Craft::$app = $originalApplication;
            \Yii::$app = $originalApplication;
        }
    }

    /** @param list<string> $codes */
    private function cleanupImportedFixture(array $codes, string $filename): void
    {
        $rows = (new Query())
            ->select(['id', 'slug'])
            ->from('{{%shortlinkmanager}}')
            ->where(['code' => $codes])
            ->all();
        foreach ($rows as $row) {
            $element = ShortLink::find()->id((int)$row['id'])->status(null)->trashed(null)->one();
            if ($element !== null) {
                Craft::$app->getElements()->deleteElement($element, true);
            }
            $this->shortLinks->invalidateShortLinkCache((int)$row['id'], (string)$row['slug']);
        }

        Db::delete('{{%shortlinkmanager_import_history}}', ['filename' => $filename]);
    }

    public function testPublicCanonicalQrUsesInheritedSavedSize(): void
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $this->installRequest();

        $this->withSettings([
            'defaultQrSize' => 1000,
            'enabledSites' => [$siteId],
            'enableQrCodeCache' => false,
        ], function(): void {
            $shortLink = $this->shortLinks->createShortLink($this->serviceOptions('public-default'));
            self::assertInstanceOf(ShortLink::class, $shortLink);
            $this->trackShortLinkForCleanup($shortLink);

            $service = new CapturingDefaultSizeQrCodeService();
            $this->swapPluginComponent('shortlink-manager', 'qrCode', $service);

            (new QrCodeController('qr-code', ShortLinkManager::$plugin))->actionGenerate($shortLink->code);

            self::assertSame(1000, $shortLink->qrCodeSize);
            self::assertSame(1000, $service->options['size'] ?? null);
        });
    }

    /** @param array<string, mixed> $overrides */
    private function controlPanelBody(string $code, array $overrides = []): array
    {
        return array_merge([
            'siteId' => Craft::$app->getSites()->getCurrentSite()->id,
            'linkType' => 'vanity',
            'code' => $code,
            'destinationType' => 'url',
            'destinationUrl' => 'https://example.com/' . $code,
            'enabled' => true,
            'qrCodeEnabled' => true,
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function serviceOptions(string $suffix, array $overrides = []): array
    {
        $code = $this->testCode($suffix);

        return array_merge([
            'linkType' => 'vanity',
            'shortLinkType' => 'manual',
            'code' => $code,
            'destinationUrl' => 'https://example.com/' . $code,
        ], $overrides);
    }

    private function testCode(string $suffix): string
    {
        return str_replace('_', '-', $this->nextTestMarker(self::MARKER, $suffix));
    }

    /** @param array<string, mixed> $bodyParams */
    private function installRequest(array $bodyParams = []): void
    {
        if ($this->originalRequest === null) {
            $this->originalRequest = Craft::$app->get('request');
        }
        if ($this->originalResponse === null) {
            $this->originalResponse = Craft::$app->get('response');
        }

        Craft::$app->set('request', new QrSizeRequest($bodyParams));
        Craft::$app->set('response', new Response());
    }
}

final class CapturingShortlinksController extends ShortlinksController
{
    /** @var array<string, mixed> */
    public array $templateVariables = [];

    public function requirePermission(string $permissionName): void
    {
    }

    public function setSuccessFlash(?string $default = null, array $settings = []): void
    {
    }

    public function setFailFlash(?string $default = null, array $settings = []): void
    {
    }

    public function redirectToPostedUrl(?object $object = null, ?string $default = null): Response
    {
        return Craft::$app->getResponse();
    }

    /** @param array<string, mixed> $variables */
    public function renderTemplate(string $template, array $variables = [], ?string $templateMode = null): Response
    {
        $this->templateVariables = $variables;

        return Craft::$app->getResponse();
    }
}

final class TestImportExportController extends ImportExportController
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

final class QrSizeRequest extends Request
{
    /** @param array<string, mixed> $fixtureBodyParams */
    public function __construct(private readonly array $fixtureBodyParams)
    {
        parent::__construct();
    }

    public function getBodyParams(): array
    {
        return $this->fixtureBodyParams;
    }

    public function getBodyParam($name, $defaultValue = null): mixed
    {
        return $this->fixtureBodyParams[$name] ?? $defaultValue;
    }

    public function getQueryParams(): array
    {
        return [];
    }

    public function getQueryParam($name, $defaultValue = null): mixed
    {
        return $defaultValue;
    }

    public function getIsPost(): bool
    {
        return true;
    }

    public function getIsAjax(): bool
    {
        return false;
    }
}

final class QrSizeApplication extends ConsoleApplication
{
    public function __construct(
        private readonly ConsoleApplication $application,
        private readonly QrSizeSession $session,
        private readonly QrSizeImportUser $user,
    ) {
    }

    public function getSession(): QrSizeSession
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

final class QrSizeImportUser extends ConsoleUser
{
    public function checkPermission(string $permissionName): bool
    {
        return true;
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

final class QrSizeSession extends Session
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

final class QrSizeFieldElement extends Element
{
    public string $fieldValue = '';
    public string $url = '';

    public static function displayName(): string
    {
        return 'QR size field element';
    }

    public function getFieldValue(string $fieldHandle): mixed
    {
        return $this->fieldValue;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }
}

final class CapturingDefaultSizeQrCodeService extends QrCodeService
{
    /** @var array<string, mixed> */
    public array $options = [];

    public function generateQrCode(string $url, array $options = []): string
    {
        $this->options = $options;

        return '<svg xmlns="http://www.w3.org/2000/svg"></svg>';
    }
}
