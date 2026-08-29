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
use craft\web\View;
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
     * @return array{complete: bool, missing: list<string>, setupUrl: string, ipSaltConfigured: bool, templatesReady: bool, templateStatuses: array<int, array{key: string, label: string, setting: string, template: string, source: string, destination: string, destinationDir: string, destinationDirExists: bool, destinationExists: bool, exists: bool}>}
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
     * @return array<int, array{key: string, label: string, setting: string, template: string, source: string, destination: string, destinationDir: string, destinationDirExists: bool, destinationExists: bool, exists: bool}>
     */
    public function templateStatuses(Settings $settings): array
    {
        $templates = [
            [
                'key' => 'redirect',
                'label' => Craft::t('shortlink-manager', 'Redirect Template'),
                'setting' => 'redirectTemplate',
                'template' => $settings->getResolvedRedirectTemplate(),
                'source' => 'vendor/lindemannrock/craft-shortlink-manager/src/templates/redirect.twig',
            ],
            [
                'key' => 'expired',
                'label' => Craft::t('shortlink-manager', 'Expired Template'),
                'setting' => 'expiredTemplate',
                'template' => $settings->getResolvedExpiredTemplate(),
                'source' => 'vendor/lindemannrock/craft-shortlink-manager/src/templates/expired.twig',
            ],
            [
                'key' => 'qr',
                'label' => Craft::t('shortlink-manager', 'QR Code Template'),
                'setting' => 'qrTemplate',
                'template' => $settings->getResolvedQrTemplate(),
                'source' => 'vendor/lindemannrock/craft-shortlink-manager/src/templates/qr.twig',
            ],
        ];

        $statuses = [];
        foreach ($templates as $template) {
            $path = $template['template'];
            $destination = $this->copyDestination($path);
            $destinationDir = $this->destinationDirectory($path);
            $statuses[] = [
                'key' => $template['key'],
                'label' => $template['label'],
                'setting' => $template['setting'],
                'template' => $template['template'],
                'source' => $template['source'],
                'destination' => $destination,
                'destinationDir' => $destinationDir,
                'destinationDirExists' => $this->siteTemplateDirectoryExists($destinationDir),
                'destinationExists' => $this->siteTemplateFileExists($destination),
                'exists' => $this->siteTemplateExists($path, $settings),
            ];
        }

        return $statuses;
    }

    public function isIpSaltConfigured(Settings $settings): bool
    {
        $salt = trim((string) ($settings->ipHashSalt ?? ''));

        return $salt !== '' && $salt !== '$SHORTLINK_MANAGER_IP_SALT';
    }

    private function siteTemplateExists(string $template, Settings $settings): bool
    {
        if ($template === '' || str_contains($template, '..')) {
            return false;
        }

        $sites = Craft::$app->getSites();
        $enabledSiteIds = array_map('intval', $settings->getEnabledSiteIds());
        $enabledSites = array_values(array_filter(
            $sites->getAllSites(false),
            static fn($site): bool => in_array((int) $site->id, $enabledSiteIds, true),
        ));
        if ($enabledSites === []) {
            return false;
        }

        $originalSite = $sites->getCurrentSite();
        $originalLanguage = Craft::$app->language;
        $craftSiteExisted = array_key_exists('CRAFT_SITE', $_SERVER);
        $craftSite = $craftSiteExisted ? $_SERVER['CRAFT_SITE'] : null;
        $craftSiteUpperExisted = array_key_exists('CRAFT_SITE_UPPER', $_SERVER);
        $craftSiteUpper = $craftSiteUpperExisted ? $_SERVER['CRAFT_SITE_UPPER'] : null;

        try {
            foreach ($enabledSites as $site) {
                $sites->setCurrentSite($site);

                // A fresh view avoids Craft's per-request template-path cache
                // carrying one site's result into the next site's check.
                $view = new View();
                if (!$view->doesTemplateExist($template, View::TEMPLATE_MODE_SITE)) {
                    return false;
                }
            }

            return true;
        } finally {
            try {
                $sites->setCurrentSite($originalSite);
            } finally {
                Craft::$app->language = $originalLanguage;
                $this->restoreServerValue('CRAFT_SITE', $craftSiteExisted, $craftSite);
                $this->restoreServerValue('CRAFT_SITE_UPPER', $craftSiteUpperExisted, $craftSiteUpper);
            }
        }
    }

    private function restoreServerValue(string $key, bool $existed, mixed $value): void
    {
        if ($existed) {
            $_SERVER[$key] = $value;
        } else {
            unset($_SERVER[$key]);
        }
    }

    private function copyDestination(string $template): string
    {
        $fileName = basename($template);

        return 'templates/' . $template . (pathinfo($fileName, PATHINFO_EXTENSION) === '' ? '.twig' : '');
    }

    private function siteTemplateFileExists(string $destination): bool
    {
        $relativePath = trim(preg_replace('#^templates/?#', '', $destination) ?? '', '/');
        if ($relativePath === '') {
            return false;
        }

        $templatesPath = Craft::$app->getPath()->getSiteTemplatesPath();

        return is_file(
            $templatesPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
        );
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
