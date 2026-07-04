<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\shortlinkmanager\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\ShortLinkManager;

/**
 * Computes setup readiness for ShortLink Manager.
 *
 * @since 5.27.0
 */
class SetupService extends Component
{
    /**
     * @return array{complete: bool, missing: list<string>, setupUrl: string, ipSaltConfigured: bool, templatesReady: bool, templateStatuses: array<int, array{key: string, label: string, setting: string, template: string, source: string, destination: string, destinationDir: string, destinationDirExists: bool, exists: bool}>}
     */
    public function getStatus(?Settings $settings = null): array
    {
        $settings ??= ShortLinkManager::$plugin->getSettings();
        $ipSaltConfigured = $this->isIpSaltConfigured($settings);
        $templateStatuses = $this->templateStatuses($settings);
        $templatesReady = true;

        foreach ($templateStatuses as $templateStatus) {
            if (!$templateStatus['exists']) {
                $templatesReady = false;
                break;
            }
        }

        $missing = [];
        if (!$ipSaltConfigured) {
            $missing[] = 'ipSalt';
        }
        if (!$templatesReady) {
            $missing[] = 'templates';
        }

        return [
            'complete' => $missing === [],
            'missing' => $missing,
            'setupUrl' => 'shortlink-manager/setup',
            'ipSaltConfigured' => $ipSaltConfigured,
            'templatesReady' => $templatesReady,
            'templateStatuses' => $templateStatuses,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, setting: string, template: string, source: string, destination: string, destinationDir: string, destinationDirExists: bool, exists: bool}>
     */
    public function templateStatuses(Settings $settings): array
    {
        $templates = [
            [
                'key' => 'redirect',
                'label' => Craft::t('shortlink-manager', 'Redirect Template'),
                'setting' => 'redirectTemplate',
                'template' => $settings->redirectTemplate ?: 'shortlink-manager/redirect',
                'source' => 'vendor/lindemannrock/craft-shortlink-manager/src/templates/redirect.twig',
            ],
            [
                'key' => 'expired',
                'label' => Craft::t('shortlink-manager', 'Expired Template'),
                'setting' => 'expiredTemplate',
                'template' => $settings->expiredTemplate ?: 'shortlink-manager/expired',
                'source' => 'vendor/lindemannrock/craft-shortlink-manager/src/templates/expired.twig',
            ],
            [
                'key' => 'qr',
                'label' => Craft::t('shortlink-manager', 'QR Code Template'),
                'setting' => 'qrTemplate',
                'template' => $settings->qrTemplate ?: 'shortlink-manager/qr',
                'source' => 'vendor/lindemannrock/craft-shortlink-manager/src/templates/qr.twig',
            ],
        ];

        $statuses = [];
        foreach ($templates as $template) {
            $path = $this->normalizeTemplatePath($template['template']);
            $destinationDir = $this->destinationDirectory($path);
            $statuses[] = [
                'key' => $template['key'],
                'label' => $template['label'],
                'setting' => $template['setting'],
                'template' => $template['template'],
                'source' => $template['source'],
                'destination' => 'templates/' . $path . '.twig',
                'destinationDir' => $destinationDir,
                'destinationDirExists' => $this->siteTemplateDirectoryExists($destinationDir),
                'exists' => $this->siteTemplateExists($path),
            ];
        }

        return $statuses;
    }

    public function isIpSaltConfigured(Settings $settings): bool
    {
        $salt = trim((string) ($settings->ipHashSalt ?? ''));

        return $salt !== '' && $salt !== '$SHORTLINK_MANAGER_IP_SALT';
    }

    private function normalizeTemplatePath(string $template): string
    {
        $template = trim((string) App::parseEnv($template));
        $template = trim($template, '/');

        if (str_ends_with($template, '.twig')) {
            $template = substr($template, 0, -5);
        }

        return $template;
    }

    private function siteTemplateExists(string $template): bool
    {
        if ($template === '' || str_contains($template, '..')) {
            return false;
        }

        $templatesPath = Craft::$app->getPath()->getSiteTemplatesPath();
        $base = $templatesPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $template);

        // Match how Craft resolves the template at render time: any configured
        // extension (default twig/html) plus directory-style index templates
        // (e.g. shortlink-manager/redirect/index.twig).
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        foreach ($generalConfig->defaultTemplateExtensions as $extension) {
            if (is_file($base . '.' . $extension)) {
                return true;
            }

            foreach ($generalConfig->indexTemplateFilenames as $indexName) {
                if (is_file($base . DIRECTORY_SEPARATOR . $indexName . '.' . $extension)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function destinationDirectory(string $template): string
    {
        $parts = explode('/', $template);
        array_pop($parts);

        return $parts === [] ? 'templates' : 'templates/' . implode('/', $parts);
    }

    private function siteTemplateDirectoryExists(string $destinationDir): bool
    {
        $relativeDir = trim(preg_replace('#^templates/?#', '', $destinationDir) ?? '', '/');
        $templatesPath = Craft::$app->getPath()->getSiteTemplatesPath();
        $directory = $relativeDir === ''
            ? $templatesPath
            : $templatesPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

        return is_dir($directory);
    }
}
