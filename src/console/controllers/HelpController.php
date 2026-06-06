<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\console\controllers;

use lindemannrock\base\console\controllers\AbstractHelpController;

/**
 * Console help for ShortLink Manager commands.
 *
 * @since 5.20.0
 */
final class HelpController extends AbstractHelpController
{
    /**
     * @inheritdoc
     */
    protected function helpManifest(): array
    {
        return [
            'title' => 'ShortLink Manager',
            'pluginHandle' => 'shortlink-manager',
            'commandPrefixes' => [
                'php craft',
                'ddev craft',
            ],
            'summary' => 'Use these commands to generate the IP hash salt used for privacy-safe shortlink analytics.',
            'common' => [
                'security/generate-salt',
            ],
            'groups' => [
                [
                    'name' => 'security',
                    'label' => 'Security',
                    'description' => 'Generate privacy and analytics secrets.',
                    'commands' => [
                        [
                            'path' => 'security/generate-salt',
                            'summary' => 'Generate the IP hash salt.',
                            'description' => 'Generate a secure SHORTLINK_MANAGER_IP_SALT value and add it to the project .env file when possible.',
                            'examples' => [
                                'shortlink-manager/security/generate-salt',
                            ],
                            'notes' => [
                                'Run this before analytics data starts accumulating.',
                                'Use the same salt across environments if you need unique-visitor analytics continuity.',
                                'Changing an existing salt resets unique-visitor tracking because future hashes will no longer match old analytics rows.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
