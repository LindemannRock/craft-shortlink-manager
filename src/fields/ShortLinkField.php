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
use craft\helpers\Json;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * ShortLink Field
 */
class ShortLinkField extends Field implements PreviewableFieldInterface
{
    /**
     * @var string Link type (code or vanity)
     */
    public string $linkType = 'code';

    /**
     * @var bool Enable QR code
     */
    public bool $enableQrCode = true;

    /**
     * @var int Default HTTP code
     */
    public int $defaultHttpCode = 301;

    /**
     * @var bool Enable expiration
     */
    public bool $enableExpiration = false;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('shortlink-manager', 'ShortLink');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): string
    {
        return '@appicons/link.svg';
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('shortlink-manager/_fields/ShortLink_settings', [
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

        return Craft::$app->getView()->renderTemplate('shortlink-manager/_fields/ShortLink_input', [
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
        if ($element && $element->id) {
            $shortLink = ShortLinkManager::$plugin->shortLinks->getByElement($element);
        }

        if (!$shortLink) {
            return '';
        }

        return Craft::$app->getView()->renderTemplate('shortlink-manager/_fields/ShortLink_preview', [
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

        // Skip if element is a draft or doesn't have URLs
        if ($element->getIsDraft() || !$element->getSite()->hasUrls) {
            return;
        }

        $value = $element->getFieldValue($this->handle);

        // If value is empty, don't create a shortlink
        if (empty($value)) {
            return;
        }

        // Value is just the code string
        $code = is_string($value) ? $value : (is_array($value) ? ($value['code'] ?? '') : '');

        if (empty($code)) {
            return;
        }

        // Check if shortlink already exists for this element
        $existingLink = ShortLinkManager::$plugin->shortLinks->getByElement($element);

        if ($existingLink) {
            // Update existing shortlink
            $existingLink->destinationUrl = $element->getUrl() ?? '';
            $existingLink->destinationUrlHash = md5($existingLink->destinationUrl . $existingLink->siteId);
            $existingLink->code = $code;

            ShortLinkManager::$plugin->shortLinks->saveShortLink($existingLink);
        } else {
            // Check if code is already used by another shortlink
            $codeExists = ShortLinkManager::$plugin->shortLinks->getByCode($code);
            if ($codeExists) {
                // Check if it's linked to THIS element (shouldn't happen but handle it)
                if ($codeExists->elementId == $element->id && $codeExists->siteId == $element->siteId) {
                    // It's for this element, just update it
                    $codeExists->destinationUrl = $element->getUrl() ?? '';
                    $codeExists->destinationUrlHash = md5($codeExists->destinationUrl . $codeExists->siteId);
                    ShortLinkManager::$plugin->shortLinks->saveShortLink($codeExists);
                    return;
                }

                // Code used by different element/standalone - can't create
                // Log warning but don't create
                return;
            }

            // Create new shortlink
            $options = [
                'element' => $element,
                'code' => $code,
                'type' => 'vanity', // Always vanity since user entered the code
                'httpCode' => $this->defaultHttpCode,
            ];

            ShortLinkManager::$plugin->shortLinks->createShortLink($options);
        }
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
