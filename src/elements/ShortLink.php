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
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\validators\UniqueValidator;
use lindemannrock\shortlinkmanager\elements\db\ShortLinkQuery;
use lindemannrock\shortlinkmanager\records\ShortLinkRecord;
use lindemannrock\shortlinkmanager\records\ShortLinkContentRecord;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use yii\validators\RequiredValidator;
use yii\validators\UrlValidator;

/**
 * ShortLink element
 *
 * @property-read string $url
 * @property-read \craft\base\ElementInterface|null $linkedElement
 */
class ShortLink extends Element
{
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
     * @var string|null Destination URL (translatable per site)
     */
    public ?string $destinationUrl = null;

    /**
     * @var string|null Expired redirect URL (translatable per site)
     */
    public ?string $expiredRedirectUrl = null;

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
    public int $httpCode = 301;

    /**
     * @var bool Track analytics
     */
    public bool $trackAnalytics = true;

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

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $pluginName = ShortLinkManager::$plugin->getSettings()->pluginName ?? 'Short Links';
        // Singularize by removing trailing 's' if present
        $singular = preg_replace('/s$/', '', $pluginName) ?: $pluginName;
        return $singular;
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return strtolower(static::displayName());
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        $pluginName = ShortLinkManager::$plugin->getSettings()->pluginName ?? 'Short Links';
        return $pluginName;
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return strtolower(static::pluralDisplayName());
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
    const STATUS_EXPIRED = 'expired';

    /**
     * @inheritdoc
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ENABLED => Craft::t('app', 'Enabled'),
            self::STATUS_DISABLED => Craft::t('app', 'Disabled'),
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
    protected static function defineSources(string $context = null): array
    {
        $pluginName = ShortLinkManager::$plugin->getSettings()->pluginName ?? 'Short Links';

        return [
            [
                'key' => '*',
                'label' => Craft::t('shortlink-manager', 'All {pluginName}', ['pluginName' => $pluginName]),
                'criteria' => [],
                'defaultSort' => ['dateCreated', 'desc'],
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
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source): array
    {
        $actions = [];

        // Set Status
        $actions[] = SetStatus::class;

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
                'label' => Craft::t('shortlink-manager', 'Hits'),
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
            'status' => ['label' => Craft::t('app', 'Status')],
            'hits' => ['label' => Craft::t('shortlink-manager', 'Hits')],
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
            'status',
            'hits',
            'dateCreated',
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

        // If we have an ID but no content loaded yet, load it now
        if ($this->id && $this->siteId && $this->destinationUrl === null) {
            $this->loadContent();
        }

        // Normalize date values
        $this->normalizeDateTime('dateExpired');
        $this->normalizeDateTime('postDate');
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
            $this->destinationUrl = $contentRecord->destinationUrl;
            $this->expiredRedirectUrl = $contentRecord->expiredRedirectUrl;
        }
    }

    /**
     * @inheritdoc
     */
    public function afterPopulate(): void
    {
        parent::afterPopulate();

        // Load content data for current site
        $this->loadContent();
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
            'destinationUrl',
            'expiredRedirectUrl',
            'elementId',
            'elementType',
            'authorId',
            'postDate',
            'dateExpired',
            'httpCode',
            'trackAnalytics',
            'qrCodeEnabled',
            'qrCodeSize',
            'qrCodeColor',
            'qrCodeBgColor',
            'qrCodeEyeColor',
            'qrCodeFormat',
            'qrLogoId',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineAttributes(): array
    {
        return array_merge(parent::defineAttributes(), [
            'code' => null,
            'slug' => null,
            'linkType' => 'code',
            'destinationUrl' => null,
            'expiredRedirectUrl' => null,
            'elementId' => null,
            'elementType' => null,
            'dateExpired' => null,
            'authorId' => null,
            'postDate' => null,
            'httpCode' => 301,
            'trackAnalytics' => true,
            'hits' => 0,
            'qrCodeEnabled' => true,
            'qrCodeSize' => 256,
            'qrCodeColor' => null,
            'qrCodeBgColor' => null,
            'qrCodeEyeColor' => null,
            'qrCodeFormat' => null,
            'qrLogoId' => null,
        ]);
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
            'destinationUrl',
            'expiredRedirectUrl',
            'elementId',
            'elementType',
            'authorId',
            'postDate',
            'dateExpired',
            'httpCode',
            'trackAnalytics',
            'qrCodeEnabled',
            'qrCodeSize',
            'qrCodeColor',
            'qrCodeBgColor',
            'qrCodeEyeColor',
            'qrCodeFormat',
            'qrLogoId',
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
        return $user->can('shortLinkManager:viewLinks');
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

        return self::STATUS_ENABLED;
    }

    /**
     * Get the shortlink URL
     */
    public function getUrl(): string
    {
        $settings = ShortLinkManager::$plugin->getSettings();
        $customDomain = $settings->customDomain;

        if (!empty($customDomain)) {
            return rtrim($customDomain, '/') . '/' . $this->slug;
        }

        $slugPrefix = $settings->slugPrefix;
        return UrlHelper::siteUrl($slugPrefix . '/' . $this->slug, null, null, $this->siteId);
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

        return Craft::$app->elements->getElementById($this->elementId, $this->elementType, $this->siteId);
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

        // Get logo ID - use per-link logo or fall back to default
        $logoId = $this->qrLogoId ?: $settings->defaultQrLogoId;

        // Merge per-link settings with options
        $qrOptions = array_merge([
            'size' => $this->qrCodeSize,
            'color' => str_replace('#', '', $this->qrCodeColor ?: $settings->defaultQrColor),
            'bg' => str_replace('#', '', $this->qrCodeBgColor ?: $settings->defaultQrBgColor),
            'eyeColor' => $this->qrCodeEyeColor ? str_replace('#', '', $this->qrCodeEyeColor) : null,
            'format' => $this->qrCodeFormat,
            'logo' => $logoId,
        ], $options);

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

        // Get logo ID - use per-link logo or fall back to default
        $logoId = $this->qrLogoId ?: $settings->defaultQrLogoId;

        // Merge per-link settings with options
        $qrOptions = array_merge([
            'size' => $this->qrCodeSize,
            'color' => str_replace('#', '', $this->qrCodeColor ?: $settings->defaultQrColor),
            'bg' => str_replace('#', '', $this->qrCodeBgColor ?: $settings->defaultQrBgColor),
            'eyeColor' => $this->qrCodeEyeColor ? str_replace('#', '', $this->qrCodeEyeColor) : null,
            'format' => $this->qrCodeFormat,
            'logo' => $logoId,
        ], $options);

        return ShortLinkManager::$plugin->qrCode->generateQrCode($this->getUrl(), $qrOptions);
    }

    /**
     * Get QR code URL for this shortlink (for use in templates)
     *
     * @param array $options Optional parameters to override defaults
     * @return string QR code action URL
     */
    public function getQrCodeUrl(array $options = []): string
    {
        $settings = ShortLinkManager::$plugin->getSettings();

        $params = array_merge([
            'linkId' => $this->id,
            'size' => $this->qrCodeSize,
            'color' => str_replace('#', '', $this->qrCodeColor ?: $settings->defaultQrColor),
            'bg' => str_replace('#', '', $this->qrCodeBgColor ?: $settings->defaultQrBgColor),
            'format' => $this->qrCodeFormat ?: $settings->defaultQrFormat,
            'margin' => $settings->defaultQrMargin,
            'moduleStyle' => $settings->qrModuleStyle,
            'eyeStyle' => $settings->qrEyeStyle,
            'eyeColor' => $this->qrCodeEyeColor ? str_replace('#', '', $this->qrCodeEyeColor) : ($settings->qrEyeColor ? str_replace('#', '', $settings->qrEyeColor) : null),
        ], $options);

        // Add logo ID if logos are enabled and one is set
        if ($settings->enableQrLogo) {
            $logoId = $this->qrLogoId ?: $settings->defaultQrLogoId;
            if ($logoId) {
                $params['logo'] = $logoId;
            }
        }

        return \craft\helpers\UrlHelper::actionUrl('shortlink-manager/qr-code/generate', $params);
    }

    /**
     * Get QR code display page URL (frontend template page)
     *
     * @param array $options Optional parameters to override defaults
     * @return string QR code page URL
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
        $qrPrefix = $settings->qrPrefix ?? 'qr';

        return \craft\helpers\UrlHelper::siteUrl("{$qrPrefix}/{$this->code}/view", $params);
    }

    /**
     * @inheritdoc
     */
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
    protected static function defineIndexUrl(string $source = null, ?string $siteHandle = null): ?string
    {
        return 'shortlink-manager/shortlinks';
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['code', 'destinationUrl', 'linkType'], RequiredValidator::class];
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
            }
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
        $rules[] = [['trackAnalytics', 'qrCodeEnabled'], 'boolean'];
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
        // If propagating and data is empty, load it from records
        if ($this->propagating && $this->id) {
            if (empty($this->code)) {
                $record = ShortLinkRecord::findOne($this->id);
                if ($record) {
                    $this->code = $record->code;
                    $this->slug = $record->slug;
                    $this->linkType = $record->linkType;
                }
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

        // Generate code for auto-generated links
        if (!$this->id && $this->linkType === 'code' && empty($this->code)) {
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
            if (!$this->destinationUrl && $this->duplicateOf->destinationUrl) {
                $this->destinationUrl = $this->duplicateOf->destinationUrl;
            }

            // Generate unique slug
            $baseSlug = $this->duplicateOf->slug ?: $this->slug;
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
            $record->code = $this->code;
            $record->slug = $this->slug;
            $record->linkType = $this->linkType;
            $record->elementId = $this->elementId;
            $record->elementType = $this->elementType;
            $record->dateExpired = $this->dateExpired;
            $record->authorId = $this->authorId;
            $record->postDate = $this->postDate;
            $record->httpCode = $this->httpCode;
            $record->trackAnalytics = $this->trackAnalytics;
            $record->hits = $this->hits;
            $record->qrCodeEnabled = $this->qrCodeEnabled;
            $record->qrCodeSize = $this->qrCodeSize;
            $record->qrCodeColor = $this->qrCodeColor;
            $record->qrCodeBgColor = $this->qrCodeBgColor;
            $record->qrCodeEyeColor = $this->qrCodeEyeColor;
            $record->qrCodeFormat = $this->qrCodeFormat;
            $record->qrLogoId = $this->qrLogoId;

            if (!$record->save(false)) {
                \Craft::error('Failed to save ShortLinkRecord: ' . print_r($record->getErrors(), true), __METHOD__);
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

            // Save content - use existing value if destinationUrl not loaded
            if (!$this->destinationUrl && $contentRecord->id) {
                // Keep existing destinationUrl if this is just a status change
                // (destinationUrl wasn't loaded from the form)
            } else {
                $contentRecord->destinationUrl = $this->destinationUrl ?: '';
            }
            $contentRecord->expiredRedirectUrl = $this->expiredRedirectUrl;

            if (!$contentRecord->save(false)) {
                Craft::error('Failed to save content record', __METHOD__, ['errors' => $contentRecord->getErrors()]);
            }
        }

        parent::afterSave($isNew);
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

            case 'postDate':
                return $this->postDate ? Html::tag('span', $this->postDate->format('M j, Y'), [
                    'title' => $this->postDate->format('D, M j, Y g:i A'),
                ]) : '—';

            case 'dateExpired':
                if (!$this->dateExpired) {
                    return '—';
                }
                $isPast = $this->dateExpired < new \DateTime();
                return Html::tag('span', $this->dateExpired->format('M j, Y'), [
                    'title' => $this->dateExpired->format('D, M j, Y g:i A'),
                    'class' => $isPast ? 'error' : '',
                ]);
        }

        return parent::getTableAttributeHtml($attribute);
    }

    /**
     * Render SEOmatic tracking code for this shortlink
     *
     * @param string $eventType Event type to track (redirect or qr_scan)
     * @return \Twig\Markup|null
     */
    public function renderSeomaticTracking(string $eventType = 'redirect'): ?\Twig\Markup
    {
        return ShortLinkManager::$plugin->integration->renderSeomaticTracking($this, $eventType);
    }
}
