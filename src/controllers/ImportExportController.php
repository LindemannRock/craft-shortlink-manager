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
use lindemannrock\base\helpers\AssetVolumeHelper;
use lindemannrock\base\helpers\CsvImportHelper;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\ExportHelper;
use lindemannrock\base\helpers\SlugHandleHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\records\ImportHistoryRecord;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Handles CSV import/export of short links with history tracking.
 *
 * @since 5.15.0
 */
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
        $this->requirePermission('shortLinkManager:manageImportExport');

        $canImport = $this->canImport();
        $canExport = $this->canExport();
        $canClearHistory = $this->canClearHistory();

        /** @var ImportHistoryRecord[] $records */
        $records = ImportHistoryRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(20)
            ->all();

        $history = [];
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

        return $this->renderTemplate('shortlink-manager/import-export/index', [
            'canImport' => $canImport,
            'canExport' => $canExport,
            'canClearHistory' => $canClearHistory,
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
            'folder', 'tags',
            'trackAnalytics', 'passQueryParams', 'directRedirect',
            'qrCodeEnabled', 'qrCodeSize', 'qrCodeColor', 'qrCodeBgColor', 'qrCodeEyeColor', 'qrCodeFormat', 'qrLogoId',
            'postDate', 'dateExpired',
        ];

        // Pre-fetch all sites keyed by ID
        $sitesById = [];
        foreach (Craft::$app->getSites()->getAllSites() as $s) {
            $sitesById[$s->id] = $s;
        }

        $shortlinks = ShortLink::find()->site('*')->status(null)->orderBy(['elements.dateCreated' => SORT_DESC])->all();
        foreach ($shortlinks as $shortLink) {
            $site = $sitesById[$shortLink->siteId] ?? null;
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
                'folder' => ShortLinkManager::$plugin->taxonomy->getFolderNameById($shortLink->folderId) ?? '',
                'tags' => implode(', ', $shortLink->tagNames),
                'trackAnalytics' => $shortLink->trackAnalytics ? '1' : '0',
                'passQueryParams' => $shortLink->passQueryParams === null ? '' : ($shortLink->passQueryParams ? '1' : '0'),
                'directRedirect' => $shortLink->directRedirect === null ? '' : ($shortLink->directRedirect ? '1' : '0'),
                'qrCodeEnabled' => $shortLink->qrCodeEnabled ? '1' : '0',
                'qrCodeSize' => $shortLink->qrCodeSize,
                'qrCodeColor' => $shortLink->qrCodeColor,
                'qrCodeBgColor' => $shortLink->qrCodeBgColor,
                'qrCodeEyeColor' => $shortLink->qrCodeEyeColor,
                'qrCodeFormat' => $shortLink->qrCodeFormat,
                'qrLogoId' => $shortLink->qrLogoId,
                'postDate' => $shortLink->postDate,
                'dateExpired' => $shortLink->dateExpired,
            ];
        }

        if (empty($rows)) {
            Craft::$app->getSession()->setError(Craft::t('shortlink-manager', 'No shortlinks to export.'));
            return $this->redirect('shortlink-manager/import-export');
        }

        $settings = ShortLinkManager::$plugin->getSettings();
        $filename = ExportHelper::filename($settings, ['export'], 'csv');

        return ExportHelper::dispatchTable($rows, $headers, 'csv', $filename, ['postDate', 'dateExpired']);
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
        $defaultSiteId = Craft::$app->getSites()->getCurrentSite()->id;

        $existingSlugs = (new \craft\db\Query())
            ->select(['slug'])
            ->from('{{%shortlinkmanager}}')
            ->column();
        $existingLookup = array_fill_keys(array_map('strtolower', $existingSlugs), true);
        $seenImportRows = [];

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
                'httpCode' => 302,
                'enabled' => true,
                'siteId' => null,
                'folder' => '',
                'tags' => [],
                'trackAnalytics' => true,
                'passQueryParams' => null,
                'directRedirect' => null,
                'qrCodeEnabled' => true,
                'qrCodeSize' => 256,
                'qrCodeColor' => null,
                'qrCodeBgColor' => null,
                'qrCodeEyeColor' => null,
                'qrCodeFormat' => null,
                'qrLogoId' => null,
                'postDate' => null,
                'dateExpired' => null,
            ];

            foreach ($columnMap as $colIndex => $fieldName) {
                if (!isset($row[$colIndex])) {
                    continue;
                }
                $value = trim((string)$row[$colIndex]);

                if ($fieldName === 'enabled' || $fieldName === 'trackAnalytics' || $fieldName === 'qrCodeEnabled') {
                    $item[$fieldName] = in_array(strtolower($value), ['1', 'true', 'yes', 'enabled'], true);
                } elseif ($fieldName === 'passQueryParams' || $fieldName === 'directRedirect') {
                    if ($value === '') {
                        $item[$fieldName] = null;
                    } else {
                        $item[$fieldName] = in_array(strtolower($value), ['1', 'true', 'yes', 'enabled'], true);
                    }
                } elseif ($fieldName === 'httpCode' || $fieldName === 'siteId' || $fieldName === 'elementId') {
                    $item[$fieldName] = $value === '' ? null : (int)$value;
                } elseif ($fieldName === 'qrCodeSize' || $fieldName === 'qrLogoId') {
                    $item[$fieldName] = $value === '' ? null : (int)$value;
                } elseif ($fieldName === 'siteHandle') {
                    $site = Craft::$app->getSites()->getSiteByHandle($value);
                    if ($site) {
                        $item['siteId'] = $site->id;
                    }
                } elseif ($fieldName === 'folder') {
                    $item['folder'] = CsvImportHelper::stripFormulaEscapePrefix($value);
                } elseif ($fieldName === 'tags') {
                    $item['tags'] = $this->parseTagList($value);
                } elseif (in_array($fieldName, ['postDate', 'dateExpired'], true)) {
                    $item[$fieldName] = $this->parseDateOrNull($value);
                } else {
                    $item[$fieldName] = CsvImportHelper::stripFormulaEscapePrefix($value);
                }
            }

            if (!empty($item['qrCodeSize'])) {
                $item['qrCodeSize'] = max(100, min(1000, (int)$item['qrCodeSize']));
            } else {
                $item['qrCodeSize'] = 256;
            }

            $item['qrCodeColor'] = $this->normalizeHexColor($item['qrCodeColor'] ?? null);
            $item['qrCodeBgColor'] = $this->normalizeHexColor($item['qrCodeBgColor'] ?? null);
            $item['qrCodeEyeColor'] = $this->normalizeHexColor($item['qrCodeEyeColor'] ?? null);

            if (!in_array((string)($item['qrCodeFormat'] ?? ''), ['', 'png', 'svg'], true)) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'code' => $item['code'] ?: '-',
                    'destinationUrl' => $item['destinationUrl'] ?: '-',
                    'error' => Craft::t('shortlink-manager', 'QR format must be png or svg'),
                ];
                continue;
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
            $resolvedSiteId = (int)($item['siteId'] ?: $defaultSiteId);

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

            $importRowKey = strtolower($slug) . '|' . $resolvedSiteId;
            if (isset($seenImportRows[$importRowKey])) {
                $duplicateRows[] = [
                    'code' => $item['code'],
                    'destinationUrl' => $item['destinationUrl'],
                    'reason' => 'Duplicate row for same code and site',
                ];
                continue;
            }

            $item['resolvedSiteId'] = $resolvedSiteId;
            $validRows[] = $item;
            $seenImportRows[$importRowKey] = true;
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

        /** @var array<string, array<int, array<string, mixed>>> $rowsBySlug */
        $rowsBySlug = [];
        foreach ($previewData['validRows'] as $row) {
            $slug = $this->generateSlugFromCode((string)($row['code'] ?? ''));
            if ($slug === '') {
                $failed++;
                continue;
            }
            $rowsBySlug[$slug][] = $row;
        }

        foreach ($rowsBySlug as $slugRows) {
            try {
                $primaryRow = $slugRows[0];
                $siteId = (int)($primaryRow['resolvedSiteId'] ?? $primaryRow['siteId'] ?? Craft::$app->getSites()->getCurrentSite()->id);
                $site = Craft::$app->getSites()->getSiteById($siteId);
                if (!$site) {
                    $failed++;
                    continue;
                }

                $shortLink = new ShortLink();
                $shortLink->siteId = $siteId;
                $shortLink->shortLinkType = $this->normalizeShortLinkType((string)($primaryRow['shortLinkType'] ?? 'manual'));
                $shortLink->linkType = $primaryRow['linkType'] ?: 'vanity';
                $shortLink->code = (string)$primaryRow['code'];
                $shortLink->elementId = !empty($primaryRow['elementId']) ? (int)$primaryRow['elementId'] : null;
                $shortLink->elementType = $this->normalizeElementType($primaryRow['elementType'] ?? null);
                $shortLink->destinationUrl = (string)($primaryRow['destinationUrl'] ?? '');

                if ($shortLink->shortLinkType === 'auto') {
                    if (!$shortLink->elementId) {
                        $failed += count($slugRows);
                        continue;
                    }

                    $element = Craft::$app->getElements()->getElementById(
                        $shortLink->elementId,
                        $shortLink->elementType,
                        '*'
                    );
                    if (!$element || !$element->getUrl()) {
                        $failed += count($slugRows);
                        continue;
                    }

                    $shortLink->elementType = get_class($element);
                    // Always derive destination from linked element for field-managed links.
                    $shortLink->destinationUrl = (string)$element->getUrl();
                } else {
                    $shortLink->elementId = null;
                    $shortLink->elementType = null;
                }

                $shortLink->httpCode = (int)($primaryRow['httpCode'] ?: 302);
                $shortLink->folderId = ShortLinkManager::$plugin->taxonomy->getOrCreateFolderByName((string)($primaryRow['folder'] ?? '')) ?: null;
                $shortLink->setTagNames($this->parseTagList($primaryRow['tags'] ?? []));
                $shortLink->trackAnalytics = (bool)$primaryRow['trackAnalytics'];
                $shortLink->passQueryParams = $primaryRow['passQueryParams'] !== null ? (bool)$primaryRow['passQueryParams'] : null;
                $shortLink->directRedirect = $primaryRow['directRedirect'] !== null ? (bool)$primaryRow['directRedirect'] : null;
                $shortLink->qrCodeEnabled = (bool)$primaryRow['qrCodeEnabled'];
                $shortLink->qrCodeSize = max(100, min(1000, (int)($primaryRow['qrCodeSize'] ?: 256)));
                $shortLink->qrCodeColor = $this->normalizeHexColor($primaryRow['qrCodeColor'] ?? null);
                $shortLink->qrCodeBgColor = $this->normalizeHexColor($primaryRow['qrCodeBgColor'] ?? null);
                $shortLink->qrCodeEyeColor = $this->normalizeHexColor($primaryRow['qrCodeEyeColor'] ?? null);
                $shortLink->qrCodeFormat = in_array((string)($primaryRow['qrCodeFormat'] ?? ''), ['png', 'svg'], true)
                    ? (string)$primaryRow['qrCodeFormat']
                    : null;
                // Validate the imported logo against the configured volume + the
                // importing user's viewAssets permission, rather than trusting the
                // raw asset ID from the CSV row.
                $shortLink->qrLogoId = AssetVolumeHelper::validateAssetId(
                    $primaryRow['qrLogoId'] ?? null,
                    ShortLinkManager::$plugin->getSettings()->qrLogoVolumeUid,
                );
                $shortLink->postDate = $primaryRow['postDate'] instanceof \DateTime ? $primaryRow['postDate'] : null;
                $shortLink->dateExpired = $primaryRow['dateExpired'] instanceof \DateTime ? $primaryRow['dateExpired'] : null;
                $shortLink->setEnabledForSite((bool)$primaryRow['enabled']);

                if (!ShortLinkManager::$plugin->shortLinks->saveShortLink($shortLink)) {
                    $failed += count($slugRows);
                    continue;
                }

                $imported++;

                // Apply additional site-specific rows for the same code/slug.
                foreach (array_slice($slugRows, 1) as $siteRow) {
                    $siteRowSiteId = (int)($siteRow['resolvedSiteId'] ?? $siteRow['siteId'] ?? 0);
                    if ($siteRowSiteId <= 0) {
                        $failed++;
                        continue;
                    }

                    $siteVariant = ShortLink::find()
                        ->id($shortLink->id)
                        ->siteId($siteRowSiteId)
                        ->status(null)
                        ->one();

                    if (!$siteVariant) {
                        $failed++;
                        continue;
                    }

                    $siteVariant->siteId = $siteRowSiteId;
                    $siteVariant->setEnabledForSite((bool)$siteRow['enabled']);
                    $siteVariant->folderId = $shortLink->folderId;
                    $siteVariant->setTagNames($shortLink->tagNames);

                    if ($shortLink->shortLinkType === 'auto') {
                        $siteVariant->elementId = $shortLink->elementId;
                        $siteVariant->elementType = $shortLink->elementType;

                        $siteElement = Craft::$app->getElements()->getElementById(
                            (int)$shortLink->elementId,
                            (string)$shortLink->elementType,
                            $siteRowSiteId
                        );

                        $siteVariant->destinationUrl = $siteElement ? (string)($siteElement->getUrl() ?? '') : '';
                    } else {
                        $siteVariant->elementId = null;
                        $siteVariant->elementType = null;
                        $siteVariant->destinationUrl = (string)($siteRow['destinationUrl'] ?? '');
                    }

                    if (!ShortLinkManager::$plugin->shortLinks->saveShortLink($siteVariant)) {
                        $failed++;
                        continue;
                    }

                    $imported++;
                }
            } catch (\Throwable) {
                $failed += count($slugRows);
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

        $pluginName = ShortLinkManager::$plugin->getSettings()->getPluralLowerDisplayName();

        if ($failed > 0) {
            Craft::$app->getSession()->setNotice(Craft::t('shortlink-manager', 'Import completed: {imported} {pluginName} imported, {failed} failed.', [
                'imported' => $imported,
                'pluginName' => $pluginName,
                'failed' => $failed,
            ]));
        } else {
            Craft::$app->getSession()->setNotice(Craft::t('shortlink-manager', 'Import completed: {imported} {pluginName} imported.', [
                'imported' => $imported,
                'pluginName' => $pluginName,
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

    private function requireImportPermission(): void
    {
        if (!$this->canImport()) {
            throw new ForbiddenHttpException(Craft::t('shortlink-manager', 'User does not have permission to import shortlinks.'));
        }
    }

    private function requireExportPermission(): void
    {
        if (!$this->canExport()) {
            throw new ForbiddenHttpException(Craft::t('shortlink-manager', 'User does not have permission to export shortlinks.'));
        }
    }

    private function requireClearImportHistoryPermission(): void
    {
        if (!$this->canClearHistory()) {
            throw new ForbiddenHttpException(Craft::t('shortlink-manager', 'User does not have permission to clear import history.'));
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

    private function canClearHistory(): bool
    {
        return Craft::$app->getUser()->checkPermission('shortLinkManager:clearImportHistory');
    }

    private function generateSlugFromCode(string $code): string
    {
        return SlugHandleHelper::normalizeSlug($code, '');
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

        // Validate against Craft's registered element types instead of raw class_exists()
        // to avoid autoloading side-effects from user-supplied class names in CSV
        $registeredTypes = Craft::$app->getElements()->getAllElementTypes();
        return in_array($trimmed, $registeredTypes, true) ? $trimmed : null;
    }

    private function parseDateOrNull(string $value): ?\DateTime
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            // Exported CSV dates are in Craft timezone; normalize to UTC for persistence.
            $localDate = DateFormatHelper::toCraftTimezone($value, false);
            if ($localDate === null) {
                return null;
            }

            $utcDate = clone $localDate;
            $utcDate->setTimezone(new \DateTimeZone('UTC'));

            return $utcDate;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function parseTagList(mixed $value): array
    {
        $rawValues = [];

        if (is_array($value)) {
            $rawValues = $value;
        } elseif (is_string($value)) {
            $trimmed = trim(CsvImportHelper::stripFormulaEscapePrefix($value));
            if ($trimmed !== '') {
                $rawValues = preg_split('/\s*,\s*/', $trimmed) ?: [];
            }
        }

        $normalized = array_map(
            static fn(mixed $item): string => trim((string)$item),
            $rawValues
        );

        return array_values(array_unique(array_filter($normalized, static fn(string $name): bool => $name !== '')));
    }

    private function normalizeHexColor(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] !== '#') {
            $trimmed = '#' . $trimmed;
        }

        return preg_match('/^#[0-9A-F]{6}$/i', $trimmed) ? strtoupper($trimmed) : null;
    }
}
