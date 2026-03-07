<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 */

namespace lindemannrock\shortlinkmanager\controllers;

use Craft;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\web\Controller;
use craft\web\UploadedFile;
use lindemannrock\base\helpers\CsvImportHelper;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\ExportHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\records\ImportHistoryRecord;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class ImportExportController extends Controller
{
    use LoggingTrait;

    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
    }

    public function actionIndex(): Response
    {
        $this->requireAnyImportExportPermission();

        $canImport = $this->canImport();
        $canExport = $this->canExport();
        $canViewHistory = $this->canViewHistory();

        $history = [];
        if ($canViewHistory) {
            /** @var ImportHistoryRecord[] $records */
            $records = ImportHistoryRecord::find()
                ->orderBy(['dateCreated' => SORT_DESC])
                ->limit(20)
                ->all();

            foreach ($records as $record) {
                $user = Craft::$app->getUsers()->getUserById($record->userId);
                $history[] = [
                    'formattedDate' => DateFormatHelper::formatDatetime($record->dateCreated),
                    'user' => $user?->username ?? Craft::t('shortlink-manager', 'Unknown'),
                    'filename' => $record->filename,
                    'formattedSize' => $record->filesize
                        ? Craft::$app->getFormatter()->asShortSize($record->filesize, 2)
                        : '-',
                    'imported' => (int)$record->imported,
                    'failed' => (int)$record->failed,
                ];
            }
        }

        return $this->renderTemplate('shortlink-manager/import-export/index', [
            'canImport' => $canImport,
            'canExport' => $canExport,
            'canViewHistory' => $canViewHistory,
            'importHistory' => $history,
            'importLimits' => [
                'maxRows' => CsvImportHelper::DEFAULT_MAX_ROWS,
                'maxBytes' => CsvImportHelper::DEFAULT_MAX_BYTES,
            ],
        ]);
    }

    public function actionExport(): Response
    {
        $this->requirePostRequest();
        $this->requireExportPermission();

        $rows = [];
        $headers = [
            'code', 'shortLinkType', 'linkType', 'destinationUrl', 'elementId', 'elementType', 'httpCode', 'enabled', 'siteId', 'siteHandle',
            'trackAnalytics', 'qrCodeEnabled', 'dateCreated', 'dateUpdated',
        ];

        $shortlinks = ShortLink::find()->site('*')->status(null)->orderBy(['elements.dateCreated' => SORT_DESC])->all();
        foreach ($shortlinks as $shortLink) {
            $site = Craft::$app->getSites()->getSiteById($shortLink->siteId);
            $rows[] = [
                'code' => $shortLink->code,
                'shortLinkType' => $shortLink->shortLinkType,
                'linkType' => $shortLink->linkType,
                'destinationUrl' => $shortLink->destinationUrl,
                'elementId' => $shortLink->elementId,
                'elementType' => $shortLink->elementType,
                'httpCode' => $shortLink->httpCode,
                'enabled' => $shortLink->getEnabledForSite($shortLink->siteId) ? '1' : '0',
                'siteId' => $shortLink->siteId,
                'siteHandle' => $site?->handle,
                'trackAnalytics' => $shortLink->trackAnalytics ? '1' : '0',
                'qrCodeEnabled' => $shortLink->qrCodeEnabled ? '1' : '0',
                'dateCreated' => $shortLink->dateCreated?->format('Y-m-d H:i:s'),
                'dateUpdated' => $shortLink->dateUpdated?->format('Y-m-d H:i:s'),
            ];
        }

        if (empty($rows)) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'No shortlinks to export.'));
            return $this->redirect('shortlink-manager/import-export');
        }

        $settings = ShortLinkManager::$plugin->getSettings();
        $filename = ExportHelper::filename($settings, ['export'], 'csv');

        return ExportHelper::toCsv($rows, $headers, $filename, ['dateCreated', 'dateUpdated']);
    }

    public function actionUpload(): Response
    {
        $this->requirePostRequest();
        $this->requireImportPermission();

        $file = UploadedFile::getInstanceByName('csvFile');
        if (!$file) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Please select a CSV file to upload.'));
            return $this->redirect('shortlink-manager/import-export');
        }

        $delimiter = (string)Craft::$app->getRequest()->getBodyParam('delimiter', 'auto');
        $detectDelimiter = $delimiter === 'auto';
        $delimiter = $detectDelimiter ? null : $delimiter;

        try {
            $parsed = CsvImportHelper::parseUpload($file, [
                'maxRows' => CsvImportHelper::DEFAULT_MAX_ROWS,
                'maxBytes' => CsvImportHelper::DEFAULT_MAX_BYTES,
                'delimiter' => $delimiter,
                'detectDelimiter' => $detectDelimiter,
            ]);

            Craft::$app->getSession()->set('shortlink-import', [
                'headers' => $parsed['headers'],
                'allRows' => $parsed['allRows'],
                'rowCount' => $parsed['rowCount'],
                'filename' => $file->name,
                'filesize' => $file->size,
            ]);

            return $this->redirect('shortlink-manager/import-export/map');
        } catch (\Throwable $e) {
            $this->logError('Failed to parse shortlink CSV', ['error' => $e->getMessage()]);
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Failed to parse CSV: {error}', ['error' => $e->getMessage()]));
            return $this->redirect('shortlink-manager/import-export');
        }
    }

    public function actionMap(): Response
    {
        $this->requireImportPermission();

        $importData = Craft::$app->getSession()->get('shortlink-import');
        if (!$importData || !isset($importData['allRows'])) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'No import data found. Please upload a CSV file.'));
            return $this->redirect('shortlink-manager/import-export');
        }

        return $this->renderTemplate('shortlink-manager/import-export/map', [
            'headers' => $importData['headers'],
            'previewRows' => array_slice($importData['allRows'], 0, 5),
            'rowCount' => $importData['rowCount'],
        ]);
    }

    public function actionPreview(): Response
    {
        $this->requireImportPermission();

        if (!Craft::$app->getRequest()->getIsPost()) {
            $previewData = Craft::$app->getSession()->get('shortlink-preview');
            if (!$previewData) {
                Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'No preview data found. Please map columns first.'));
                return $this->redirect('shortlink-manager/import-export');
            }
            return $this->renderTemplate('shortlink-manager/import-export/preview', $previewData);
        }

        $importData = Craft::$app->getSession()->get('shortlink-import');
        if (!$importData || !isset($importData['allRows'])) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Import session expired. Please upload the file again.'));
            return $this->redirect('shortlink-manager/import-export');
        }

        $mapping = Craft::$app->getRequest()->getBodyParam('mapping', []);
        $columnMap = [];
        foreach ($mapping as $colIndex => $fieldName) {
            if (!empty($fieldName)) {
                $columnMap[(int)$colIndex] = $fieldName;
            }
        }

        $mappedFields = array_values($columnMap);
        if (!in_array('code', $mappedFields, true)) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Code must be mapped.'));
            return $this->redirect('shortlink-manager/import-export/map');
        }

        $validRows = [];
        $duplicateRows = [];
        $errorRows = [];

        $existingSlugs = (new \craft\db\Query())
            ->select(['slug'])
            ->from('{{%shortlinkmanager}}')
            ->column();
        $existingLookup = array_fill_keys(array_map('strtolower', $existingSlugs), true);

        $rowNumber = 1;
        foreach ($importData['allRows'] as $row) {
            $rowNumber++;

            $item = [
                'code' => '',
                'shortLinkType' => 'manual',
                'linkType' => 'vanity',
                'destinationUrl' => '',
                'elementId' => null,
                'elementType' => null,
                'httpCode' => 301,
                'enabled' => true,
                'siteId' => null,
                'trackAnalytics' => true,
                'qrCodeEnabled' => true,
            ];

            foreach ($columnMap as $colIndex => $fieldName) {
                if (!isset($row[$colIndex])) {
                    continue;
                }
                $value = trim((string)$row[$colIndex]);

                if ($fieldName === 'enabled' || $fieldName === 'trackAnalytics' || $fieldName === 'qrCodeEnabled') {
                    $item[$fieldName] = in_array(strtolower($value), ['1', 'true', 'yes', 'enabled'], true);
                } elseif ($fieldName === 'httpCode' || $fieldName === 'siteId' || $fieldName === 'elementId') {
                    $item[$fieldName] = $value === '' ? null : (int)$value;
                } elseif ($fieldName === 'siteHandle') {
                    $site = Craft::$app->getSites()->getSiteByHandle($value);
                    if ($site) {
                        $item['siteId'] = $site->id;
                    }
                } else {
                    $item[$fieldName] = CsvImportHelper::stripFormulaEscapePrefix($value);
                }
            }

            if ($item['code'] === '') {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'code' => '-',
                    'destinationUrl' => $item['destinationUrl'] ?: '-',
                    'error' => 'Missing required field: code',
                ];
                continue;
            }

            $item['shortLinkType'] = $this->normalizeShortLinkType((string)($item['shortLinkType'] ?? 'manual'));
            $item['linkType'] = in_array((string)$item['linkType'], ['code', 'vanity'], true) ? (string)$item['linkType'] : 'vanity';

            if ($item['shortLinkType'] === 'auto') {
                if (empty($item['elementId'])) {
                    $errorRows[] = [
                        'rowNumber' => $rowNumber,
                        'code' => $item['code'],
                        'destinationUrl' => $item['destinationUrl'] ?: '-',
                        'error' => 'Field-managed shortlinks require elementId',
                    ];
                    continue;
                }

                $elementType = $this->normalizeElementType($item['elementType']);
                $element = Craft::$app->getElements()->getElementById((int)$item['elementId'], $elementType, '*');
                if (!$element) {
                    $errorRows[] = [
                        'rowNumber' => $rowNumber,
                        'code' => $item['code'],
                        'destinationUrl' => $item['destinationUrl'] ?: '-',
                        'error' => 'Linked element not found for field-managed shortlink',
                    ];
                    continue;
                }

                $elementUrl = $element->getUrl();
                if (empty($elementUrl)) {
                    $errorRows[] = [
                        'rowNumber' => $rowNumber,
                        'code' => $item['code'],
                        'destinationUrl' => $item['destinationUrl'] ?: '-',
                        'error' => 'Linked element has no URL',
                    ];
                    continue;
                }

                $item['elementType'] = get_class($element);
                if ($item['destinationUrl'] === '') {
                    $item['destinationUrl'] = (string)$elementUrl;
                }
            } else {
                $item['elementId'] = null;
                $item['elementType'] = null;

                if ($item['destinationUrl'] === '') {
                    $errorRows[] = [
                        'rowNumber' => $rowNumber,
                        'code' => $item['code'],
                        'destinationUrl' => '-',
                        'error' => 'Missing required field: destinationUrl',
                    ];
                    continue;
                }

                if (!preg_match('#^https?://|^/#i', (string)$item['destinationUrl'])) {
                    $errorRows[] = [
                        'rowNumber' => $rowNumber,
                        'code' => $item['code'],
                        'destinationUrl' => $item['destinationUrl'],
                        'error' => 'Invalid destination URL (must start with https://, http://, or /)',
                    ];
                    continue;
                }
            }

            $slug = $this->generateSlugFromCode((string)$item['code']);
            if (isset($existingLookup[strtolower($slug)])) {
                $duplicateRows[] = [
                    'code' => $item['code'],
                    'destinationUrl' => $item['destinationUrl'],
                    'reason' => 'Code/slug already exists',
                ];
                continue;
            }

            $validRows[] = $item;
            $existingLookup[strtolower($slug)] = true;
        }

        $summary = [
            'totalRows' => count($importData['allRows']),
            'validRows' => count($validRows),
            'duplicates' => count($duplicateRows),
            'errors' => count($errorRows),
        ];

        $previewData = [
            'validRows' => $validRows,
            'duplicateRows' => $duplicateRows,
            'errorRows' => $errorRows,
            'summary' => $summary,
        ];

        Craft::$app->getSession()->set('shortlink-preview', $previewData);

        return $this->renderTemplate('shortlink-manager/import-export/preview', $previewData);
    }

    public function actionImport(): ?Response
    {
        $this->requirePostRequest();
        $this->requireImportPermission();

        $previewData = Craft::$app->getSession()->get('shortlink-preview');
        $importData = Craft::$app->getSession()->get('shortlink-import');

        if (!$previewData || !$importData) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'Import session expired. Please upload the file again.'));
            return $this->redirect('shortlink-manager/import-export');
        }

        $imported = 0;
        $failed = 0;

        foreach ($previewData['validRows'] as $row) {
            try {
                $siteId = (int)($row['siteId'] ?: Craft::$app->getSites()->getCurrentSite()->id);
                $site = Craft::$app->getSites()->getSiteById($siteId);
                if (!$site) {
                    $failed++;
                    continue;
                }

                $shortLink = new ShortLink();
                $shortLink->siteId = $siteId;
                $shortLink->shortLinkType = $this->normalizeShortLinkType((string)($row['shortLinkType'] ?? 'manual'));
                $shortLink->linkType = $row['linkType'] ?: 'vanity';
                $shortLink->code = (string)$row['code'];
                $shortLink->elementId = !empty($row['elementId']) ? (int)$row['elementId'] : null;
                $shortLink->elementType = $this->normalizeElementType($row['elementType'] ?? null);
                $shortLink->destinationUrl = (string)($row['destinationUrl'] ?? '');

                if ($shortLink->shortLinkType === 'auto') {
                    if (!$shortLink->elementId) {
                        $failed++;
                        continue;
                    }

                    $element = Craft::$app->getElements()->getElementById(
                        $shortLink->elementId,
                        $shortLink->elementType,
                        '*'
                    );
                    if (!$element || !$element->getUrl()) {
                        $failed++;
                        continue;
                    }

                    $shortLink->elementType = get_class($element);
                    if ($shortLink->destinationUrl === '') {
                        $shortLink->destinationUrl = (string)$element->getUrl();
                    }
                } else {
                    $shortLink->elementId = null;
                    $shortLink->elementType = null;
                }

                $shortLink->httpCode = (int)($row['httpCode'] ?: 301);
                $shortLink->trackAnalytics = (bool)$row['trackAnalytics'];
                $shortLink->qrCodeEnabled = (bool)$row['qrCodeEnabled'];
                $shortLink->setEnabledForSite((bool)$row['enabled']);

                if (!ShortLinkManager::$plugin->shortLinks->saveShortLink($shortLink)) {
                    $failed++;
                    continue;
                }

                $imported++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        Db::insert('{{%shortlinkmanager_import_history}}', [
            'userId' => Craft::$app->getUser()->getId(),
            'filename' => $importData['filename'] ?? null,
            'filesize' => $importData['filesize'] ?? null,
            'imported' => $imported,
            'failed' => $failed,
            'dateCreated' => Db::prepareDateForDb(new \DateTime()),
            'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
            'uid' => StringHelper::UUID(),
        ]);

        Craft::$app->getSession()->remove('shortlink-import');
        Craft::$app->getSession()->remove('shortlink-preview');

        if ($failed > 0) {
            Craft::$app->getSession()->setNotice(Craft::t('shortlink-manager', 'Import completed: {imported} imported, {failed} failed.', [
                'imported' => $imported,
                'failed' => $failed,
            ]));
        } else {
            Craft::$app->getSession()->setNotice(Craft::t('shortlink-manager', 'Import completed: {imported} shortlinks imported.', [
                'imported' => $imported,
            ]));
        }

        return $this->redirect('shortlink-manager/import-export');
    }

    public function actionClearLogs(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requireClearImportHistoryPermission();

        try {
            Db::delete(ImportHistoryRecord::tableName());
            return $this->asJson(['success' => true]);
        } catch (\Throwable) {
            return $this->asJson(['success' => false, 'error' => Craft::t('shortlink-manager', 'Failed to clear import history.')]);
        }
    }

    private function requireAnyImportExportPermission(): void
    {
        if ($this->canImport() || $this->canExport() || $this->canViewHistory() || $this->canClearHistory()) {
            return;
        }
        throw new ForbiddenHttpException('User does not have permission to access import/export.');
    }

    private function requireImportPermission(): void
    {
        if (!$this->canImport()) {
            throw new ForbiddenHttpException('User does not have permission to import shortlinks.');
        }
    }

    private function requireExportPermission(): void
    {
        if (!$this->canExport()) {
            throw new ForbiddenHttpException('User does not have permission to export shortlinks.');
        }
    }

    private function requireClearImportHistoryPermission(): void
    {
        if (!$this->canClearHistory()) {
            throw new ForbiddenHttpException('User does not have permission to clear import history.');
        }
    }

    private function canImport(): bool
    {
        return Craft::$app->getUser()->checkPermission('shortLinkManager:importLinks');
    }

    private function canExport(): bool
    {
        return Craft::$app->getUser()->checkPermission('shortLinkManager:exportLinks');
    }

    private function canViewHistory(): bool
    {
        return Craft::$app->getUser()->checkPermission('shortLinkManager:viewImportHistory');
    }

    private function canClearHistory(): bool
    {
        return Craft::$app->getUser()->checkPermission('shortLinkManager:clearImportHistory');
    }

    private function generateSlugFromCode(string $code): string
    {
        $slug = strtolower($code);
        $slug = preg_replace('/[^a-z0-9\-_]/', '-', $slug) ?? '';
        $slug = preg_replace('/-+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private function normalizeShortLinkType(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['auto', 'field', 'field-managed'], true)) {
            return 'auto';
        }

        return 'manual';
    }

    private function normalizeElementType(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $aliases = [
            'entry' => \craft\elements\Entry::class,
            'asset' => \craft\elements\Asset::class,
            'category' => \craft\elements\Category::class,
            'tag' => \craft\elements\Tag::class,
            'user' => \craft\elements\User::class,
            'product' => 'craft\\commerce\\elements\\Product',
            'variant' => 'craft\\commerce\\elements\\Variant',
        ];

        $key = strtolower($trimmed);
        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        return class_exists($trimmed) ? $trimmed : null;
    }
}
