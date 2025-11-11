<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\twigextensions;

use lindemannrock\shortlinkmanager\ShortLinkManager;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Plugin Name Twig Extension
 *
 * Provides centralized access to plugin name variations in Twig templates.
 *
 * Usage in templates:
 * - {{ pluginHelper.displayName }}             // "ShortLink" (singular, no Manager)
 * - {{ pluginHelper.pluralDisplayName }}       // "ShortLinks" (plural, no Manager)
 * - {{ pluginHelper.fullName }}                // "ShortLink Manager" (as configured)
 * - {{ pluginHelper.lowerDisplayName }}        // "shortlink" (lowercase singular)
 * - {{ pluginHelper.pluralLowerDisplayName }}  // "shortlinks" (lowercase plural)
 */
class PluginNameExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'ShortLink Manager - Plugin Name Helper';
    }

    /**
     * Make plugin name helper available as global Twig variable
     *
     * @return array
     */
    public function getGlobals(): array
    {
        return [
            'shortlinkHelper' => new PluginNameHelper(),
        ];
    }
}

/**
 * Plugin Name Helper
 *
 * Helper class that exposes Settings methods as properties for clean Twig syntax.
 */
class PluginNameHelper
{
    /**
     * Get display name (singular, without "Manager")
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return ShortLinkManager::$plugin->getSettings()->getDisplayName();
    }

    /**
     * Get plural display name (without "Manager")
     *
     * @return string
     */
    public function getPluralDisplayName(): string
    {
        return ShortLinkManager::$plugin->getSettings()->getPluralDisplayName();
    }

    /**
     * Get full plugin name (as configured)
     *
     * @return string
     */
    public function getFullName(): string
    {
        return ShortLinkManager::$plugin->getSettings()->getFullName();
    }

    /**
     * Get lowercase display name (singular, without "Manager")
     *
     * @return string
     */
    public function getLowerDisplayName(): string
    {
        return ShortLinkManager::$plugin->getSettings()->getLowerDisplayName();
    }

    /**
     * Get lowercase plural display name (without "Manager")
     *
     * @return string
     */
    public function getPluralLowerDisplayName(): string
    {
        return ShortLinkManager::$plugin->getSettings()->getPluralLowerDisplayName();
    }

    /**
     * Magic getter to allow property-style access in Twig
     * Enables: {{ pluginHelper.displayName }} instead of {{ pluginHelper.getDisplayName() }}
     *
     * @param string $name
     * @return string|null
     */
    public function __get(string $name): ?string
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return null;
    }
}
