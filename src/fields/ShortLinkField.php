<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * ShortLink Field
 *
 * @since 5.0.0
 */
class ShortLinkField extends Field implements PreviewableFieldInterface
{
    use LoggingTrait;
    /**
     * @var string Link type (code or vanity)
     * @since 5.0.0
     */
    public string $linkType = 'code';

    /**
     * @var int Default HTTP code
     * @since 5.0.0
     */
    public int $defaultHttpCode = 301;

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
    public static function icon(): string
    {
        return '@appicons/link-simple.svg';
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('shortlink-manager/_components/fields/ShortLinkField/settings', [
            'field' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        // Get existing shortlink for this element if it exists
        $shortLink = null;
        if ($element && $element->id) {
            $shortLink = ShortLinkManager::$plugin->shortLinks->getByElement($element);
        }

        return Craft::$app->getView()->renderTemplate('shortlink-manager/_components/fields/ShortLinkField/input', [
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
            'element' => $element,
            'shortLink' => $shortLink,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getPreviewHtml($value, ElementInterface $element): string
    {
        // Get existing shortlink for this element
        $shortLink = null;
        if ($element->id) {
            $shortLink = ShortLinkManager::$plugin->shortLinks->getByElement($element);
        }

        if (!$shortLink) {
            return '';
        }

        return Craft::$app->getView()->renderTemplate('shortlink-manager/_components/fields/ShortLinkField/preview', [
            'shortLink' => $shortLink,
            'field' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function afterElementSave(ElementInterface $element, bool $isNew): void
    {
        parent::afterElementSave($element, $isNew);

        // Skip if element is a draft, revision, or doesn't have URLs
        if ($element->getIsDraft() || $element->getIsRevision() || !$element->getSite()->hasUrls) {
            return;
        }

        // Skip if this is a propagating save (Craft saving to other sites)
        // We only want to create/update once for the canonical element
        if ($element->propagating) {
            return;
        }

        $value = $element->getFieldValue($this->handle);

        // Value is just the code string (or empty for auto-generated)
        $code = is_string($value) ? $value : '';

        // Use field setting for link type
        $linkType = $this->linkType;

        // Check if shortlink already exists for this element (pass siteId explicitly)
        $existingLink = ShortLinkManager::$plugin->shortLinks->getByElement($element, $element->siteId);

        // Debug logging
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
        $this->logInfo('afterElementSave called', [
            'elementId' => $element->id,
            'siteId' => $element->siteId,
            'existingLink' => $existingLink ? $existingLink->id : 'null',
            'code' => $code,
            'linkType' => $linkType,
        ]);

        // If existing link, update it
        if ($existingLink) {
            // Only update destination URL (auto-synced from element)
            // NEVER update code - it's managed in ShortLink Manager only
            $existingLink->destinationUrl = $element->getUrl() ?? '';

            ShortLinkManager::$plugin->shortLinks->saveShortLink($existingLink);
            return;
        }

        // No existing shortlink found - create one

        // For vanity links, code is required
        if ($linkType === 'vanity' && empty($code)) {
            return; // Can't create vanity link without code
        }

        // For auto-generated, code will be empty - that's fine, it will be generated

        // Check if code is already used (only for vanity links with code provided)
        if (!empty($code)) {
            $codeExists = ShortLinkManager::$plugin->shortLinks->getByCode($code);
            if ($codeExists) {
                // Check if it's linked to THIS element (shouldn't happen but handle it)
                if ($codeExists->elementId == $element->id && $codeExists->siteId == $element->siteId) {
                    // It's for this element, just update it
                    $codeExists->destinationUrl = $element->getUrl() ?? '';
                    ShortLinkManager::$plugin->shortLinks->saveShortLink($codeExists);
                    return;
                }

                // Code used by different element/standalone - can't create
                // Log warning but don't create
                return;
            }
        }

        // Create new shortlink
        $options = [
            'element' => $element,
            'type' => $linkType,
            'shortLinkType' => 'auto', // Field-managed shortlinks are 'auto'
            'httpCode' => $this->defaultHttpCode,
        ];

        // Only add code if provided (for vanity links)
        if (!empty($code)) {
            $options['code'] = $code;
        }

        ShortLinkManager::$plugin->shortLinks->createShortLink($options);
    }

    /**
     * @inheritdoc
     */
    public function afterElementDelete(ElementInterface $element): void
    {
        parent::afterElementDelete($element);

        if (!$element->getIsCanonical()) {
            return;
        }

        // Delete associated shortlink
        $shortLink = ShortLinkManager::$plugin->shortLinks->getByElement($element);
        if ($shortLink) {
            ShortLinkManager::$plugin->shortLinks->deleteShortLink($shortLink->id);
        }
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        // Ignore deprecated properties that may still be in database
        if (in_array($name, ['enableQrCode', 'enableExpiration'])) {
            return;
        }

        parent::__set($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null): mixed
    {
        // Just return the string value
        return is_string($value) ? $value : '';
    }

    /**
     * @inheritdoc
     */
    public function serializeValue($value, ElementInterface $element = null): mixed
    {
        // Just return the string value
        return is_string($value) ? $value : '';
    }
}
