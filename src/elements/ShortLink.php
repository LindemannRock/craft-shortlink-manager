<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Duplicate;
use craft\elements\actions\Restore;
use craft\elements\actions\SetStatus;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\models\FieldLayout;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\elements\actions\AddTagsAction;
use lindemannrock\shortlinkmanager\elements\actions\ClearFolderAction;
use lindemannrock\shortlinkmanager\elements\actions\ClearTagsAction;
use lindemannrock\shortlinkmanager\elements\actions\RemoveTagsAction;
use lindemannrock\shortlinkmanager\elements\actions\SetFolderAction;
use lindemannrock\shortlinkmanager\elements\db\ShortLinkQuery;
use lindemannrock\shortlinkmanager\records\ShortLinkContentRecord;
use lindemannrock\shortlinkmanager\records\ShortLinkRecord;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\validators\RequiredValidator;

/**
 * ShortLink element
 *
 * @property-read string $url
 * @property-read \craft\base\ElementInterface|null $linkedElement
 * @since 5.0.0
 */
class ShortLink extends Element
{
    use LoggingTrait;

    // Properties
    // =========================================================================

    /**
     * @var string|null Code (user-facing short code)
     */
    public ?string $code = null;

    /**
     * @var string|null Slug (sanitized version of code, used in URLs)
     */
    public ?string $slug = null;

    /**
     * @var string Link type: 'code' (auto-generated) or 'vanity' (custom)
     */
    public string $linkType = 'code';

    /**
     * @var string ShortLink type: 'auto' (field-managed) or 'manual' (standalone)
     */
    public string $shortLinkType = 'manual';

    /**
     * @var string|null Destination URL (translatable per site)
     */
    public ?string $destinationUrl = null;

    /**
     * @var string|null Expired redirect URL (translatable per site)
     */
    public ?string $expiredRedirectUrl = null;

    /**
     * @var string|null Expired message (translatable per site)
     */
    public ?string $expiredMessage = null;

    /**
     * @var int|null Linked element ID
     */
    public ?int $elementId = null;

    /**
     * @var string|null Linked element type
     */
    public ?string $elementType = null;

    /**
     * @var \DateTime|null Expiry date
     */
    public ?\DateTime $dateExpired = null;

    /**
     * @var int|null Author ID
     */
    public ?int $authorId = null;

    /**
     * @var \DateTime|null Post date
     */
    public ?\DateTime $postDate = null;

    /**
     * @var int HTTP redirect code (301, 302, 307, 308)
     */
    public int $httpCode = 302;

    /**
     * @var bool Track analytics
     */
    public bool $trackAnalytics = true;

    /**
     * @var bool|null Pass query params to destination (null = use global setting)
     */
    public ?bool $passQueryParams = null;

    /**
     * @var bool|null Direct HTTP redirect without template render (null = use global setting)
     * @since 5.12.0
     */
    public ?bool $directRedirect = null;

    /**
     * @var int Total hits/clicks
     */
    public int $hits = 0;

    /**
     * @var bool QR code enabled
     */
    public bool $qrCodeEnabled = true;

    /**
     * @var int QR code size
     */
    public int $qrCodeSize = 256;

    /**
     * @var string|null QR code color
     */
    public ?string $qrCodeColor = null;

    /**
     * @var string|null QR code background color
     */
    public ?string $qrCodeBgColor = null;

    /**
     * @var string|null QR code eye color
     */
    public ?string $qrCodeEyeColor = null;

    /**
     * @var string|null QR code format override
     */
    public ?string $qrCodeFormat = null;

    /**
     * @var int|null QR code logo asset ID (overrides default)
     */
    public ?int $qrLogoId = null;

    /**
     * @var int|null Folder ID (plugin-internal taxonomy)
     */
    public ?int $folderId = null;

    /**
     * @var array<int, string> Tag names (plugin-internal taxonomy)
     */
    public array $tagNames = [];

    private bool $_tagNamesLoaded = false;

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return ShortLinkManager::$plugin->getSettings()->getDisplayName();
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return ShortLinkManager::$plugin->getSettings()->getLowerDisplayName();
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return ShortLinkManager::$plugin->getSettings()->getPluralDisplayName();
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return ShortLinkManager::$plugin->getSettings()->getPluralLowerDisplayName();
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'shortLink';
    }

    /**
     * @inheritdoc
     */
    public static function trackChanges(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function supportedSites(): array
    {
        $settings = ShortLinkManager::getInstance()->getSettings();
        $enabledSiteIds = $settings->getEnabledSiteIds();

        // Return array of site IDs that support this element type
        return array_map(function($siteId) {
            return ['siteId' => $siteId, 'enabledByDefault' => true];
        }, $enabledSiteIds);
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        return $this->code ?? $this->slug ?? '';
    }

    /**
     * @inheritdoc
     */
    public static function hasUris(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {
        // Support all sites, with independent enabled status per site
        return array_map(function($siteId) {
            return [
                'siteId' => $siteId,
                'propagateAll' => false, // Don't auto-propagate changes to other sites
            ];
        }, Craft::$app->getSites()->getAllSiteIds());
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @var string Status expired
     */
    public const STATUS_EXPIRED = 'expired';

    /**
     * @var string Status pending
     */
    public const STATUS_PENDING = 'pending';

    /**
     * @inheritdoc
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ENABLED => Craft::t('app', 'Enabled'),
            self::STATUS_DISABLED => Craft::t('app', 'Disabled'),
            self::STATUS_PENDING => Craft::t('app', 'Pending'),
            self::STATUS_EXPIRED => Craft::t('app', 'Expired'),
        ];
    }

    /**
     * @inheritdoc
     * @return ShortLinkQuery
     */
    public static function find(): ElementQueryInterface
    {
        return new ShortLinkQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(?string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('shortlink-manager', 'All {pluginName}', ['pluginName' => ShortLinkManager::$plugin->getSettings()->getPluralDisplayName()]),
                'criteria' => [],
                'defaultSort' => ['postDate', 'desc'],
            ],
            [
                'key' => 'code',
                'label' => Craft::t('shortlink-manager', 'Auto-generated'),
                'criteria' => ['linkType' => 'code'],
            ],
            [
                'key' => 'vanity',
                'label' => Craft::t('shortlink-manager', 'Vanity URLs'),
                'criteria' => ['linkType' => 'vanity'],
            ],
        ];

        $folderRows = ShortLinkManager::$plugin->taxonomy->getFoldersForIndex();

        if (!empty($folderRows)) {
            $sources[] = ['heading' => Craft::t('shortlink-manager', 'Folders')];
            $sources[] = [
                'key' => 'folder:none',
                'label' => Craft::t('shortlink-manager', 'No Folder'),
                'criteria' => ['folderId' => 0],
            ];

            foreach ($folderRows as $row) {
                $folderId = (int)$row['id'];
                $folderName = trim((string)$row['name']);
                if ($folderId <= 0 || $folderName === '') {
                    continue;
                }

                $sources[] = [
                    'key' => 'folder:' . $folderId,
                    'label' => $folderName,
                    'criteria' => ['folderId' => $folderId],
                ];
            }
        }

        $tagRows = ShortLinkManager::$plugin->taxonomy->getTagsForIndex();

        if (!empty($tagRows)) {
            $sources[] = ['heading' => Craft::t('shortlink-manager', 'Tags')];
            $sources[] = [
                'key' => 'tag:none',
                'label' => Craft::t('shortlink-manager', 'No Tags'),
                'criteria' => ['tagSlug' => '__none__'],
            ];

            foreach ($tagRows as $row) {
                $tagSlug = trim((string)$row['slug']);
                $tagName = trim((string)$row['name']);
                if ($tagSlug === '' || $tagName === '') {
                    continue;
                }

                $sources[] = [
                    'key' => 'tag:' . $tagSlug,
                    'label' => $tagName,
                    'criteria' => ['tagSlug' => $tagSlug],
                ];
            }
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source): array
    {
        $actions = [];

        // Set Status
        $actions[] = SetStatus::class;
        $actions[] = SetFolderAction::class;
        $actions[] = ClearFolderAction::class;
        $actions[] = AddTagsAction::class;
        $actions[] = RemoveTagsAction::class;
        $actions[] = ClearTagsAction::class;

        // Delete
        $actions[] = Craft::$app->elements->createAction([
            'type' => Delete::class,
            'confirmationMessage' => Craft::t('shortlink-manager', 'Are you sure you want to delete the selected short links?'),
            'successMessage' => Craft::t('shortlink-manager', 'Short links deleted.'),
        ]);

        // Duplicate
        $actions[] = Duplicate::class;

        // Restore
        $actions[] = Craft::$app->elements->createAction([
            'type' => Restore::class,
            'successMessage' => Craft::t('shortlink-manager', 'Short links restored.'),
            'partialSuccessMessage' => Craft::t('shortlink-manager', 'Some short links restored.'),
            'failMessage' => Craft::t('shortlink-manager', 'Short links not restored.'),
        ]);

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function includeSetStatusAction(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'slug' => Craft::t('shortlink-manager', 'Code/Slug'),
            [
                'label' => Craft::t('shortlink-manager', 'Interactions'),
                'orderBy' => 'shortlinkmanager.hits',
                'attribute' => 'hits',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Expiry Date'),
                'orderBy' => 'shortlinkmanager.dateExpired',
                'attribute' => 'dateExpired',
                'defaultDir' => 'asc',
            ],
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Updated'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'slug' => ['label' => Craft::t('shortlink-manager', 'Code')],
            'linkType' => ['label' => Craft::t('shortlink-manager', 'Type')],
            'destinationUrl' => ['label' => Craft::t('shortlink-manager', 'Destination')],
            'folder' => ['label' => Craft::t('shortlink-manager', 'Folder')],
            'tags' => ['label' => Craft::t('shortlink-manager', 'Tags')],
            'status' => ['label' => Craft::t('app', 'Status')],
            'hits' => ['label' => Craft::t('shortlink-manager', 'Interactions')],
            'postDate' => ['label' => Craft::t('app', 'Post Date')],
            'dateExpired' => ['label' => Craft::t('app', 'Expiry Date')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'slug',
            'linkType',
            'destinationUrl',
            'folder',
            'tags',
            'status',
            'hits',
            'postDate',
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['slug', 'destinationUrl'];
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        // Set logging handle for LoggingTrait
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);

        // If we have an ID but no content loaded yet, load it now
        if ($this->id && $this->siteId && $this->destinationUrl === null) {
            $this->loadContent();
        }

        // Normalize date values
        $this->normalizeDateTime('dateExpired');
        $this->normalizeDateTime('postDate');

        // Tag names are hydrated by the query layer when elements are fetched.
        // Avoid eager-loading here — init() fires before the batch hydration path.
    }

    /**
     * Normalize a date property to DateTime object
     */
    private function normalizeDateTime(string $property): void
    {
        if ($this->$property !== null && !($this->$property instanceof \DateTime)) {
            try {
                $this->$property = DateTimeHelper::toDateTime($this->$property);
            } catch (\Exception) {
                $this->$property = null;
            }
        }
    }

    /**
     * Load content for the current site
     */
    public function loadContent(): void
    {
        if (!$this->id || !$this->siteId) {
            return;
        }

        // Skip loading from content table if this is a revision
        if ($this->getIsRevision()) {
            return;
        }

        $contentRecord = ShortLinkContentRecord::findOne([
            'shortLinkId' => $this->id,
            'siteId' => $this->siteId,
        ]);

        if ($contentRecord) {
            // Override with site-specific content
            $this->elementId = $contentRecord->elementId;
            $this->elementType = $contentRecord->elementType;
            $this->destinationUrl = $contentRecord->destinationUrl;
            $this->expiredRedirectUrl = $contentRecord->expiredRedirectUrl;
            $this->expiredMessage = $contentRecord->expiredMessage;
        }
    }

    /**
     * @inheritdoc
     */
    public function afterPopulate(): void
    {
        // Load content data for current site
        $this->loadContent();
    }

    /**
     * @param array<int, string> $tagNames
     */
    public function setTagNames(array $tagNames): void
    {
        $this->tagNames = ShortLinkManager::$plugin->taxonomy->normalizeTagNames($tagNames);
        $this->_tagNamesLoaded = true;
    }

    public function hasLoadedTagNames(): bool
    {
        return $this->_tagNamesLoaded;
    }

    /**
     * @inheritdoc
     */
    public static function defineNativeFields(): array
    {
        return [
            'code',
            'slug',
            'linkType',
            'shortLinkType',
            'destinationUrl',
            'expiredRedirectUrl',
            'expiredMessage',
            'elementId',
            'elementType',
            'authorId',
            'postDate',
            'dateExpired',
            'httpCode',
            'trackAnalytics',
            'passQueryParams',
            'directRedirect',
            'qrCodeEnabled',
            'qrCodeSize',
            'qrCodeColor',
            'qrCodeBgColor',
            'qrCodeEyeColor',
            'qrCodeFormat',
            'qrLogoId',
            'folderId',
            'tagNames',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineAttributes(): array
    {
        return [
            'code' => null,
            'slug' => null,
            'linkType' => 'code',
            'shortLinkType' => 'manual',
            'destinationUrl' => null,
            'expiredRedirectUrl' => null,
            'expiredMessage' => null,
            'elementId' => null,
            'elementType' => null,
            'dateExpired' => null,
            'authorId' => null,
            'postDate' => null,
            'httpCode' => 302,
            'trackAnalytics' => true,
            'passQueryParams' => null,
            'directRedirect' => null,
            'hits' => 0,
            'qrCodeEnabled' => true,
            'qrCodeSize' => 256,
            'qrCodeColor' => null,
            'qrCodeBgColor' => null,
            'qrCodeEyeColor' => null,
            'qrCodeFormat' => null,
            'qrLogoId' => null,
            'folderId' => null,
            'tagNames' => [],
        ];
    }

    /**
     * @inheritdoc
     */
    public function safeAttributes(): array
    {
        $attributes = parent::safeAttributes();
        return array_merge($attributes, [
            'code',
            'slug',
            'linkType',
            'shortLinkType',
            'destinationUrl',
            'expiredRedirectUrl',
            'expiredMessage',
            'elementId',
            'elementType',
            'authorId',
            'postDate',
            'dateExpired',
            'httpCode',
            'trackAnalytics',
            'passQueryParams',
            'directRedirect',
            'qrCodeEnabled',
            'qrCodeSize',
            'qrCodeColor',
            'qrCodeBgColor',
            'qrCodeEyeColor',
            'qrCodeFormat',
            'qrLogoId',
            'folderId',
            'tagNames',
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        // Get field layouts from project config
        $fieldLayouts = Craft::$app->getProjectConfig()->get('shortlink-manager.fieldLayouts') ?? [];

        if (!empty($fieldLayouts)) {
            // Get the first (and only) field layout
            $fieldLayoutUid = array_key_first($fieldLayouts);
            $fieldLayout = Craft::$app->getFields()->getLayoutByUid($fieldLayoutUid);
            if ($fieldLayout) {
                return $fieldLayout;
            }
        }

        // Fallback to getting by type (for backwards compatibility)
        return Craft::$app->fields->getLayoutByType(ShortLink::class);
    }

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
    {
        return $user->can('shortLinkManager:manageLinks');
    }

    /**
     * @inheritdoc
     */
    public function canSave(User $user): bool
    {
        if (!$this->id) {
            return $user->can('shortLinkManager:createLinks');
        }

        return $user->can('shortLinkManager:editLinks');
    }

    /**
     * @inheritdoc
     */
    public function canDelete(User $user): bool
    {
        return $user->can('shortLinkManager:deleteLinks');
    }

    /**
     * @inheritdoc
     */
    public function canDuplicate(User $user): bool
    {
        return $user->can('shortLinkManager:createLinks');
    }

    /**
     * @inheritdoc
     */
    public function canCreateDrafts(User $user): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function hasRevisions(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasDrafts(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): ?string
    {
        // Check if enabled for the current site using Craft's built-in method
        // This checks the elements_sites.enabled column
        if ($this->enabled === false) {
            return self::STATUS_DISABLED;
        }

        // Check if expired
        if ($this->isExpired()) {
            return self::STATUS_EXPIRED;
        }

        // Check if pending (future post date)
        if ($this->postDate && $this->postDate > new \DateTime()) {
            return self::STATUS_PENDING;
        }

        return self::STATUS_ENABLED;
    }

    /**
     * Get the shortlink URL
     *
     * Uses configured shortlink base URL overrides when set, otherwise falls back
     * to the element site's base URL.
     */
    public function getUrl(): string
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $slug = ltrim((string) $this->slug, '/');
        $usePrefix = (bool) ($settings->usePrefix ?? true);

        if ($usePrefix) {
            $slugPrefix = trim((string) ($settings->slugPrefix ?? 's'), '/');
            $slugPrefix = $slugPrefix !== '' ? $slugPrefix : 's';
            return $settings->buildPublicUrl($slugPrefix . '/' . $slug, $this->siteId);
        }

        return $settings->buildPublicUrl($slug, $this->siteId);
    }

    /**
     * Get the linked element
     *
     * @return \craft\base\ElementInterface|null
     */
    public function getLinkedElement(): ?\craft\base\ElementInterface
    {
        if (!$this->elementId || !$this->elementType) {
            return null;
        }

        // Search all sites since manual shortlinks can link to elements from any site
        return Craft::$app->elements->getElementById($this->elementId, $this->elementType, '*');
    }

    /**
     * Get the author user element
     *
     * @return User|null
     */
    public function getAuthor(): ?User
    {
        if ($this->authorId) {
            return User::find()->id($this->authorId)->one();
        }
        return null;
    }

    /**
     * Virtual attribute used by element index table columns.
     *
     * @return string|null
     */
    public function getFolder(): ?string
    {
        return ShortLinkManager::$plugin->taxonomy->getFolderNameById($this->folderId);
    }

    /**
     * Virtual attribute used by element index table columns.
     *
     * @return string
     */
    public function getTags(): string
    {
        return $this->renderTagsBadgeHtml();
    }

    /**
     * Render tag badges for index table cells.
     *
     * @return string
     */
    private function renderTagsBadgeHtml(): string
    {
        if (!$this->hasLoadedTagNames() && $this->id) {
            $this->setTagNames(ShortLinkManager::$plugin->taxonomy->getTagNamesForShortLink((int)$this->id));
        }

        $tagNames = $this->tagNames;
        if (empty($tagNames)) {
            return '—';
        }

        $badges = [];
        $view = Craft::$app->getView();

        foreach ($tagNames as $index => $tagName) {
            if ($index >= 5) {
                $remaining = count($tagNames) - 5;
                $badges[] = Html::tag('span', '+' . $remaining, [
                    'class' => 'status-label gray',
                    'title' => Craft::t('shortlink-manager', '{count} more tags', ['count' => $remaining]),
                ]);
                break;
            }

            try {
                $badges[] = $view->renderTemplate('lindemannrock-base/_components/badge', [
                    'label' => (string)$tagName,
                    'status' => 'gray',
                ]);
            } catch (\Throwable) {
                $badges[] = Html::tag('span', Html::encode((string)$tagName), [
                    'class' => 'status-label gray',
                ]);
            }
        }

        return Html::tag('span', implode('', $badges), [
            'style' => 'display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap;',
        ]);
    }

    /**
     * Render folder badge for index table cells.
     *
     * @return string
     */
    private function renderFolderBadgeHtml(): string
    {
        $folderName = ShortLinkManager::$plugin->taxonomy->getFolderNameById($this->folderId);
        if (!$folderName) {
            return '—';
        }

        try {
            return Craft::$app->getView()->renderTemplate('lindemannrock-base/_components/badge', [
                'label' => $folderName,
                'status' => 'blue',
            ]);
        } catch (\Throwable) {
            return Html::tag('span', Html::encode($folderName), [
                'class' => 'status-label blue',
            ]);
        }
    }

    /**
     * Check if the shortlink is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->dateExpired) {
            return false;
        }

        return $this->dateExpired < new \DateTime();
    }

    /**
     * Get analytics for this shortlink
     *
     * @param array $filters
     * @return array
     */
    public function getAnalytics(array $filters = []): array
    {
        if (!$this->id) {
            return [];
        }

        return ShortLinkManager::$plugin->analytics->getClickStats($this->id, $filters);
    }

    /**
     * Get QR code data URI
     *
     * @param array $options
     * @return string
     */
    public function getQrCodeDataUri(array $options = []): string
    {
        if (!$this->qrCodeEnabled) {
            return '';
        }

        // Get settings for fallback values
        $settings = ShortLinkManager::$plugin->getSettings();

        // Merge per-link settings with options
        $qrOptions = array_merge([
            'size' => $this->qrCodeSize,
            'color' => str_replace('#', '', $this->qrCodeColor ?: $settings->defaultQrColor),
            'bg' => str_replace('#', '', $this->qrCodeBgColor ?: $settings->defaultQrBgColor),
            'eyeColor' => $this->qrCodeEyeColor ? str_replace('#', '', $this->qrCodeEyeColor) : null,
            'format' => $this->qrCodeFormat,
        ], $options);

        // Only add logo if logos are enabled in settings
        if ($settings->enableQrLogo) {
            $logoId = $this->qrLogoId ?: $settings->defaultQrLogoId;
            if ($logoId) {
                $qrOptions['logo'] = $logoId;
            }
        }

        return ShortLinkManager::$plugin->qrCode->generateQrCodeDataUrl($this->getUrl(), $qrOptions);
    }

    /**
     * Get QR code binary data
     *
     * @param array $options
     * @return string
     */
    public function getQrCode(array $options = []): string
    {
        // Get settings for fallback values
        $settings = ShortLinkManager::$plugin->getSettings();

        if (!$this->qrCodeEnabled) {
            return '';
        }

        // Merge per-link settings with options
        $qrOptions = array_merge([
            'size' => $this->qrCodeSize,
            'color' => str_replace('#', '', $this->qrCodeColor ?: $settings->defaultQrColor),
            'bg' => str_replace('#', '', $this->qrCodeBgColor ?: $settings->defaultQrBgColor),
            'eyeColor' => $this->qrCodeEyeColor ? str_replace('#', '', $this->qrCodeEyeColor) : null,
            'format' => $this->qrCodeFormat,
        ], $options);

        // Only add logo if logos are enabled in settings
        if ($settings->enableQrLogo) {
            $logoId = $this->qrLogoId ?: $settings->defaultQrLogoId;
            if ($logoId) {
                $qrOptions['logo'] = $logoId;
            }
        }

        return ShortLinkManager::$plugin->qrCode->generateQrCode($this->getUrl(), $qrOptions);
    }

    /**
     * Get QR code URL for this shortlink (for use in templates)
     *
     * @param array $options Optional parameters to override defaults
     * @return string QR code URL (site URL with code)
     */
    public function getQrCodeUrl(array $options = []): string
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        $params = array_merge([
            'size' => $this->qrCodeSize,
            'color' => str_replace('#', '', $this->qrCodeColor ?: $settings->defaultQrColor),
            'bg' => str_replace('#', '', $this->qrCodeBgColor ?: $settings->defaultQrBgColor),
            'format' => $this->qrCodeFormat ?: $settings->defaultQrFormat,
            'margin' => $settings->defaultQrMargin,
            'moduleStyle' => $settings->qrModuleStyle,
            'eyeStyle' => $settings->qrEyeStyle,
            'eyeColor' => $this->qrCodeEyeColor ? str_replace('#', '', $this->qrCodeEyeColor) : ($settings->qrEyeColor ? str_replace('#', '', $settings->qrEyeColor) : null),
        ], $options);

        // Remove null values
        $params = array_filter($params, fn($value) => $value !== null);

        // Get the QR prefix from settings
        $qrPrefix = trim((string) ($settings->qrPrefix ?? 'qr'), '/');
        $qrPrefix = $qrPrefix !== '' ? $qrPrefix : 'qr';
        $slug = ltrim((string) $this->slug, '/');

        return $settings->buildPublicUrl("{$qrPrefix}/{$slug}", $this->siteId, $params);
    }

    /**
     * Get QR code display page URL (frontend template page)
     *
     * @param array $options Optional parameters to override defaults
     * @return string QR code page URL
     * @since 5.1.0
     */
    public function getQrCodeDisplayUrl(array $options = []): string
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        // Get the same parameters as getQrCodeUrl to ensure consistency
        $params = array_merge([
            'size' => $this->qrCodeSize,
            'color' => str_replace('#', '', $this->qrCodeColor ?: $settings->defaultQrColor),
            'bg' => str_replace('#', '', $this->qrCodeBgColor ?: $settings->defaultQrBgColor),
            'format' => $this->qrCodeFormat ?: $settings->defaultQrFormat,
            'eyeColor' => $this->qrCodeEyeColor ? str_replace('#', '', $this->qrCodeEyeColor) : ($settings->qrEyeColor ? str_replace('#', '', $settings->qrEyeColor) : null),
        ], $options);

        // Remove null values
        $params = array_filter($params, fn($value) => $value !== null);

        // Get the QR prefix from settings
        $qrPrefix = trim((string) ($settings->qrPrefix ?? 'qr'), '/');
        $qrPrefix = $qrPrefix !== '' ? $qrPrefix : 'qr';
        $slug = ltrim((string) $this->slug, '/');

        return $settings->buildPublicUrl("{$qrPrefix}/{$slug}/view", $this->siteId, $params);
    }

    /**
     * @inheritdoc
     */
    protected function cpEditUrl(): ?string
    {
        return sprintf('shortlink-manager/shortlinks/%s', $this->getCanonicalId());
    }

    /**
     * @inheritdoc
     */
    protected static function defineIndexUrl(?string $source = null, ?string $siteHandle = null): ?string
    {
        return 'shortlink-manager/shortlinks';
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['code', 'linkType'], RequiredValidator::class];
        // destinationUrl is required only when no elementId is set (custom URL mode)
        $rules[] = [['destinationUrl'], RequiredValidator::class, 'when' => fn($model) => empty($model->elementId)];
        // When elementId is set, validate that the element exists and has a URL
        $rules[] = [['elementId'], function($attribute, $params, $validator) {
            if (!empty($this->elementId) && empty($this->destinationUrl)) {
                $element = Craft::$app->elements->getElementById($this->elementId, $this->elementType, '*');
                if (!$element) {
                    $this->addError($attribute, Craft::t('shortlink-manager', 'The selected element no longer exists.'));
                } elseif (!$element->getUrl()) {
                    $this->addError($attribute, Craft::t('shortlink-manager', 'The selected element does not have a URL.'));
                }
            }
        }];
        $rules[] = [['code'], 'match', 'pattern' => '/^[a-zA-Z0-9_\-\s]+$/', 'message' => Craft::t('shortlink-manager', '{attribute} should only contain letters, numbers, underscores, hyphens, and spaces.')];
        $rules[] = [['linkType'], 'in', 'range' => ['code', 'vanity']];

        // Handle code uniqueness (check slug, since that's what's used in URLs)
        $rules[] = [
            ['code'],
            function($attribute, $params, $validator) {
                if (!$this->code) {
                    return;
                }

                // Generate what the slug would be
                $testSlug = $this->generateSlugFromCode($this->code);

                // Check if this slug already exists
                $query = (new \craft\db\Query())
                    ->from('{{%shortlinkmanager}}')
                    ->where(['slug' => $testSlug]);

                if ($this->id) {
                    $query->andWhere(['not', ['id' => $this->id]]);
                }

                if ($query->exists()) {
                    $this->addError($attribute, Craft::t('shortlink-manager', 'This code is already in use (slug: {slug}).', ['slug' => $testSlug]));
                }
            },
        ];

        // Custom URL validator that accepts both full URLs and paths
        $rules[] = [['destinationUrl', 'expiredRedirectUrl'], function($attribute, $params, $validator) {
            $url = $this->$attribute;

            // Skip if empty (expiredRedirectUrl is optional)
            if (empty($url)) {
                return;
            }

            // Allow paths starting with /
            if (str_starts_with($url, '/')) {
                return;
            }

            // Require full URLs to have a valid scheme
            if (!preg_match('/^https?:\/\/.+/', $url)) {
                $this->addError($attribute, Craft::t('shortlink-manager', 'Please enter a valid URL starting with https:// or http://, or a path starting with / (e.g., https://example.com or /page)'));
            }
        }];

        $rules[] = [['httpCode'], 'in', 'range' => [301, 302, 307, 308]];
        $rules[] = [['trackAnalytics', 'passQueryParams', 'directRedirect', 'qrCodeEnabled'], 'boolean'];
        $rules[] = [['folderId'], 'integer'];
        $rules[] = [['qrCodeSize'], 'integer', 'min' => 100, 'max' => 1000];
        $rules[] = [['qrCodeColor', 'qrCodeBgColor'], 'match', 'pattern' => '/^#[0-9A-F]{6}$/i'];
        $rules[] = [['qrCodeEyeColor'], 'match', 'pattern' => '/^#[0-9A-F]{6}$/i', 'when' => function($model) {
            return !empty($model->qrCodeEyeColor);
        }];
        $rules[] = [['qrCodeFormat'], 'in', 'range' => ['png', 'svg', null], 'allowArray' => false];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        if (!$this->hasLoadedTagNames() && $this->tagNames !== []) {
            $this->setTagNames($this->tagNames);
        }

        // If propagating and data is empty, load it from records
        if ($this->propagating && $this->id) {
            if (empty($this->code)) {
                $record = ShortLinkRecord::findOne($this->id);
                if ($record) {
                    $this->code = $record->code;
                    $this->slug = $record->slug;
                    $this->linkType = $record->linkType;
                    $this->shortLinkType = $record->shortLinkType;
                    $this->folderId = $record->folderId ? (int)$record->folderId : null;
                }
            }

            if (!$this->hasLoadedTagNames()) {
                $this->setTagNames(ShortLinkManager::$plugin->taxonomy->getTagNamesForShortLink((int)$this->id));
            }

            // Load content if not loaded
            if (empty($this->destinationUrl)) {
                $this->loadContent();
            }
        }

        // Set author and post date for new links
        if (!$this->id) {
            if (!$this->authorId) {
                $this->authorId = Craft::$app->getUser()->getId();
            }
            if (!$this->postDate) {
                $this->postDate = new \DateTime();
            }
        }

        // Generate code for auto-generated links.
        // This must also handle existing links switched from vanity -> code.
        if ($this->linkType === 'code' && empty($this->code)) {
            $settings = ShortLinkManager::$plugin->getSettings();
            $this->code = $this->generateUniqueSlug($settings->codeLength ?? 8);
        }

        // Generate slug from code (beautify/sanitize)
        if ($this->code && empty($this->slug)) {
            $this->slug = $this->generateSlugFromCode($this->code);
        } elseif ($this->code && $this->slug !== $this->generateSlugFromCode($this->code)) {
            // Code changed, regenerate slug
            $this->slug = $this->generateSlugFromCode($this->code);
        }

        // Auto-generate title from code (required for hasTitles = true)
        if (empty($this->title) && $this->code) {
            $this->title = $this->code;
        }

        // Handle duplication
        if ($this->duplicateOf && !$this->id) {
            // Ensure duplicateOf has its content loaded
            if ($this->duplicateOf instanceof ShortLink && !$this->duplicateOf->destinationUrl) {
                $this->duplicateOf->loadContent();
            }

            // Copy required fields if not set
            if (!$this->destinationUrl && $this->duplicateOf instanceof ShortLink && $this->duplicateOf->destinationUrl) {
                $this->destinationUrl = $this->duplicateOf->destinationUrl;
            }

            // Generate unique slug
            $baseSlug = ($this->duplicateOf instanceof ShortLink ? $this->duplicateOf->slug : null) ?: $this->slug;
            $testSlug = $baseSlug;
            $num = 1;

            // Keep trying until we find a unique slug
            while (true) {
                $exists = (new \craft\db\Query())
                    ->from('{{%shortlinkmanager}}')
                    ->where(['slug' => $testSlug])
                    ->exists();

                if (!$exists) {
                    break;
                }

                $testSlug = $baseSlug . '-' . $num;
                $num++;

                // Safety check to prevent infinite loop
                if ($num > 100) {
                    break;
                }
            }

            $this->slug = $testSlug;
        }

        return parent::beforeValidate();
    }

    /**
     * Generate a unique slug for auto-generated links
     */
    private function generateUniqueSlug(int $length): string
    {
        $attempts = 0;
        $maxAttempts = 10;

        do {
            $slug = $this->generateRandomSlug($length);
            $exists = (new \craft\db\Query())
                ->from('{{%shortlinkmanager}}')
                ->where(['slug' => $slug])
                ->exists();
            $attempts++;
        } while ($exists && $attempts < $maxAttempts);

        if ($exists) {
            // If we still have a collision after max attempts, append timestamp
            $slug .= '-' . time();
        }

        return $slug;
    }

    /**
     * Generate a random slug
     */
    private function generateRandomSlug(int $length): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $slug = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $slug .= $characters[random_int(0, $max)];
        }

        return $slug;
    }

    /**
     * Generate slug from code (beautify/sanitize)
     */
    private function generateSlugFromCode(string $code): string
    {
        // Sanitize: lowercase, replace spaces/special chars with hyphens
        $slug = strtolower($code);
        $slug = preg_replace('/[^a-z0-9\-_]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug); // Remove multiple hyphens
        $slug = trim($slug, '-'); // Remove leading/trailing hyphens

        return $slug;
    }

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        // CRITICAL: Always set elements.enabled = true for ShortLinks
        // We use per-site enabling (elements_sites.enabled), not global enabling
        // Craft's default behavior sets elements.enabled=false when ANY site is disabled,
        // which breaks our queries that check "elements.enabled AND elements_sites.enabled"
        $this->enabled = true;

        // Don't save QR settings that match global defaults - only save custom values
        $settings = ShortLinkManager::$plugin->getSettings();

        // Normalize and compare colors (strip # and check if empty or matches default)
        $normalizeColor = fn($color) => $color ? strtolower(ltrim($color, '#')) : '';

        $thisColor = $normalizeColor($this->qrCodeColor);
        $defaultColor = $normalizeColor($settings->defaultQrColor);
        if (empty($thisColor) || $thisColor === $defaultColor) {
            $this->qrCodeColor = null;
        }

        $thisBgColor = $normalizeColor($this->qrCodeBgColor);
        $defaultBgColor = $normalizeColor($settings->defaultQrBgColor);
        if (empty($thisBgColor) || $thisBgColor === $defaultBgColor) {
            $this->qrCodeBgColor = null;
        }

        $thisEyeColor = $normalizeColor($this->qrCodeEyeColor);
        $defaultEyeColor = $normalizeColor($settings->qrEyeColor);
        if (empty($thisEyeColor) || $thisEyeColor === $defaultEyeColor) {
            $this->qrCodeEyeColor = null;
        }

        if (empty($this->qrLogoId) || $this->qrLogoId === $settings->defaultQrLogoId) {
            $this->qrLogoId = null;
        }

        return parent::beforeSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        // Skip saving to custom tables if this is a revision or resaving (status change, etc.)
        if (!$this->getIsRevision() && !$this->resaving) {
            if (!$isNew) {
                $record = ShortLinkRecord::findOne($this->id);

                if (!$record) {
                    throw new \Exception('Invalid short link ID: ' . $this->id);
                }
            } else {
                $record = new ShortLinkRecord();
                $record->id = $this->id;
            }

            // Save non-translatable fields to main table
            // Note: elementId and elementType are now per-site (stored in content table)
            $record->code = $this->code;
            $record->slug = $this->slug;
            $record->linkType = $this->linkType;
            $record->shortLinkType = $this->shortLinkType;
            $record->dateExpired = $this->dateExpired;
            $record->authorId = $this->authorId;
            $record->postDate = $this->postDate;
            $record->httpCode = $this->httpCode;
            $record->trackAnalytics = $this->trackAnalytics;
            $record->passQueryParams = $this->passQueryParams;
            $record->directRedirect = $this->directRedirect;
            $record->hits = $this->hits;
            $record->qrCodeEnabled = $this->qrCodeEnabled;
            $record->qrCodeSize = $this->qrCodeSize;
            $record->qrCodeColor = $this->qrCodeColor;
            $record->qrCodeBgColor = $this->qrCodeBgColor;
            $record->qrCodeEyeColor = $this->qrCodeEyeColor;
            $record->qrCodeFormat = $this->qrCodeFormat;
            $record->qrLogoId = $this->qrLogoId;
            $record->folderId = $this->folderId;

            if (!$record->save(false)) {
                $this->logError('Failed to save ShortLinkRecord', ['errors' => $record->getErrors()]);
            }

            // Save translatable fields to content table
            $contentRecord = ShortLinkContentRecord::findOne([
                'shortLinkId' => $this->id,
                'siteId' => $this->siteId,
            ]);

            if (!$contentRecord) {
                $contentRecord = new ShortLinkContentRecord();
                $contentRecord->shortLinkId = $this->id;
                $contentRecord->siteId = $this->siteId;
            }

            // Save translatable fields - elementId, elementType, and destinationUrl
            $contentRecord->elementId = $this->elementId;
            $contentRecord->elementType = $this->elementType;

            // Save content - use existing value if destinationUrl not loaded
            if (!$this->destinationUrl && $contentRecord->id) {
                // Keep existing destinationUrl if this is just a status change
                // (destinationUrl wasn't loaded from the form)
            } else {
                $contentRecord->destinationUrl = $this->destinationUrl ?: '';
            }
            $contentRecord->expiredRedirectUrl = $this->expiredRedirectUrl;
            $contentRecord->expiredMessage = $this->expiredMessage;

            if (!$contentRecord->save(false)) {
                $this->logError('Failed to save content record', ['errors' => $contentRecord->getErrors()]);
            }

            // For auto shortlinks (field-managed), always sync elementId/elementType to ALL sites.
            // Import/save flows can run via propagation, and skipping here can leave cross-site URLs stale.
            if ($this->shortLinkType === 'auto' && $this->elementId) {
                $this->syncElementToAllSites();
            }
        }

        if (!$this->getIsRevision() && $this->hasLoadedTagNames()) {
            ShortLinkManager::$plugin->taxonomy->syncShortLinkTagsByNames(
                (int)$this->id,
                $this->tagNames
            );
        }

        parent::afterSave($isNew);
    }

    /**
     * Sync elementId/elementType to all sites for auto shortlinks
     *
     * For field-managed shortlinks, the linked element is the same across all sites,
     * but each site may have a different destination URL (resolved from the element).
     */
    private function syncElementToAllSites(): void
    {
        if (!$this->id || !$this->elementId) {
            return;
        }

        $settings = ShortLinkManager::$plugin->getSettings();
        $enabledSiteIds = $settings->getEnabledSiteIds();

        // Get the linked element to resolve destination URLs per site
        $linkedElement = $this->getLinkedElement();

        foreach ($enabledSiteIds as $siteId) {
            // Skip the current site (already saved)
            if ($siteId == $this->siteId) {
                continue;
            }

            // Find or create content record for this site
            $contentRecord = ShortLinkContentRecord::findOne([
                'shortLinkId' => $this->id,
                'siteId' => $siteId,
            ]);

            if (!$contentRecord) {
                $contentRecord = new ShortLinkContentRecord();
                $contentRecord->shortLinkId = $this->id;
                $contentRecord->siteId = $siteId;
            }

            // Set the same elementId/elementType for all sites
            $contentRecord->elementId = $this->elementId;
            $contentRecord->elementType = $this->elementType;

            // Resolve destination URL for this site from the linked element
            if ($linkedElement) {
                // Get the element for this specific site
                $siteElement = Craft::$app->elements->getElementById(
                    $this->elementId,
                    $this->elementType,
                    $siteId
                );
                $contentRecord->destinationUrl = $siteElement ? ($siteElement->getUrl() ?? '') : '';
            } else {
                // Fallback to empty or existing URL
                $contentRecord->destinationUrl = $contentRecord->destinationUrl ?? '';
            }

            if (!$contentRecord->save(false)) {
                $this->logError('Failed to sync content record to site', [
                    'siteId' => $siteId,
                    'errors' => $contentRecord->getErrors(),
                ]);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        // Delete analytics data (cascade will handle this via foreign key)

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getTableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'hits':
                return $this->hits > 0 ? number_format($this->hits) : '—';

            case 'linkType':
                return $this->linkType === 'code'
                    ? Craft::t('shortlink-manager', 'Auto')
                    : Craft::t('shortlink-manager', 'Vanity');

            case 'destinationUrl':
                if (!$this->destinationUrl) {
                    return '—';
                }
                // Truncate long URLs
                $url = $this->destinationUrl;
                if (strlen($url) > 60) {
                    $url = substr($url, 0, 57) . '...';
                }
                return Html::encode($url);

            case 'folder':
                return $this->renderFolderBadgeHtml();

            case 'tags':
                return $this->renderTagsBadgeHtml();

            case 'postDate':
                return $this->postDate ? Html::tag('span', DateFormatHelper::formatDate($this->postDate, 'medium'), [
                    'title' => DateFormatHelper::formatDatetime($this->postDate, 'long'),
                ]) : '—';

            case 'dateExpired':
                if (!$this->dateExpired) {
                    return '—';
                }
                $isPast = $this->dateExpired < new \DateTime();
                return Html::tag('span', DateFormatHelper::formatDate($this->dateExpired, 'medium'), [
                    'title' => DateFormatHelper::formatDatetime($this->dateExpired, 'long'),
                    'class' => $isPast ? 'error' : '',
                ]);
        }

        return (string)$this->$attribute;
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'folder' => $this->renderFolderBadgeHtml(),
            'tags' => $this->renderTagsBadgeHtml(),
            default => parent::attributeHtml($attribute),
        };
    }

    /**
     * Render SEOmatic tracking code for this shortlink
     *
     * @param string $eventType Event type to track (redirect or qr_scan)
     * @return \Twig\Markup|null
     * @since 5.1.0
     */
    public function renderSeomaticTracking(string $eventType = 'redirect'): ?\Twig\Markup
    {
        return ShortLinkManager::$plugin->integration->renderSeomaticTracking($this, $eventType);
    }
}
