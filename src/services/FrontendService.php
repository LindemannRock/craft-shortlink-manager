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
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use Twig\Markup;

/**
 * Frontend rendering helpers for ShortLink templates
 *
 * @since 5.23.0
 */
class FrontendService extends Component
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(ShortLinkManager::$plugin->id);
    }

    /**
     * Render the client-side tracked redirect script.
     *
     * @param string $goUrl Server-side tracking hop URL
     * @param string $shortlinkCode Shortlink code for dev debug output
     * @param bool|null $allowDebugOverride Whether ?debug=1 should stop redirects; null limits it to devMode
     * @return Markup|null HTML script tag or null if rendering fails
     */
    public function renderRedirectScript(string $goUrl, string $shortlinkCode, ?bool $allowDebugOverride = null): ?Markup
    {
        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);

            $html = $view->renderTemplate('shortlink-manager/_frontend/redirect', [
                'goUrl' => $goUrl,
                'shortlinkCode' => $shortlinkCode,
                'skipDebugRedirect' => $allowDebugOverride ?? Craft::$app->getConfig()->getGeneral()->devMode,
            ]);

            return new Markup($html, 'UTF-8');
        } catch (\Throwable $e) {
            $this->logError('Failed to render redirect script', [
                'error' => $e->getMessage(),
                'goUrl' => $goUrl,
                'shortlinkCode' => $shortlinkCode,
            ]);

            return null;
        } finally {
            $view->setTemplateMode($oldMode);
        }
    }
}
