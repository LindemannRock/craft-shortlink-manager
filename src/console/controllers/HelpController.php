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
            'summary' => 'Use these commands to generate the IP hash salt and copy starter templates for ShortLink Manager setup.',
            'common' => [
                'security/generate-salt',
                'setup/copy-templates',
            ],
            'groups' => [
                [
                    'name' => 'setup',
                    'label' => 'Setup',
                    'description' => 'Copy frontend starter templates into the configured site template paths.',
                    'commands' => [
                        [
                            'path' => 'setup/copy-templates',
                            'summary' => 'Copy missing starter templates.',
                            'description' => 'Copy bundled redirect, expired, and QR templates into the configured paths in your site templates folder.',
                            'usageOptions' => '[--template=<redirect|expired|qr>] [--overwrite]',
                            'options' => [
                                [
                                    'name' => '--template',
                                    'description' => 'Copy only one template: redirect, expired, or qr.',
                                ],
                                [
                                    'name' => '--overwrite',
                                    'description' => 'Replace existing destination templates without prompting.',
                                ],
                            ],
                            'examples' => [
                                'shortlink-manager/setup/copy-templates',
                                'shortlink-manager/setup/copy-templates --template=redirect',
                                'shortlink-manager/setup/copy-templates --template=qr --overwrite',
                            ],
                            'notes' => [
                                'By default, existing destination templates are skipped.',
                                'The command respects custom template paths configured in settings or config.',
                                'Review and customize copied templates before going live.',
                            ],
                        ],
                    ],
                ],
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
