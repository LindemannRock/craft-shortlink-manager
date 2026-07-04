<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use Craft;
use craft\helpers\FileHelper;
use lindemannrock\shortlinkmanager\services\SetupService;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;

/**
 * Pins setup-readiness detection.
 *
 * Covers the template-existence resolution (which must match how Craft resolves
 * the frontend templates at render time — any configured extension plus
 * directory-style index templates) and the IP salt readiness gate (which must
 * mirror the runtime hash gate in AnalyticsTrackingService).
 *
 * @since 5.27.0
 */
final class SetupServiceTest extends TestCase
{
    private SetupService $setup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setup = ShortLinkManager::$plugin->setup;
    }

    public function testTemplateStatusDetectsExtensionAndIndexVariants(): void
    {
        $templatesPath = Craft::$app->getPath()->getSiteTemplatesPath();
        $dir = 'sl-test-setup-' . bin2hex(random_bytes(4));
        $absDir = $templatesPath . DIRECTORY_SEPARATOR . $dir;
        FileHelper::createDirectory($absDir);

        try {
            // Direct .twig file: templates/<dir>/redirect.twig
            file_put_contents($absDir . DIRECTORY_SEPARATOR . 'redirect.twig', '{# test #}');
            // Direct .html file: templates/<dir>/expired.html
            file_put_contents($absDir . DIRECTORY_SEPARATOR . 'expired.html', '<!-- test -->');
            // Directory-style index template: templates/<dir>/qr/index.twig
            FileHelper::createDirectory($absDir . DIRECTORY_SEPARATOR . 'qr');
            file_put_contents($absDir . DIRECTORY_SEPARATOR . 'qr' . DIRECTORY_SEPARATOR . 'index.twig', '{# test #}');

            $this->withSettings([
                'redirectTemplate' => $dir . '/redirect',
                'expiredTemplate' => $dir . '/expired',
                'qrTemplate' => $dir . '/qr',
            ], function() use ($dir): void {
                $settings = ShortLinkManager::$plugin->getSettings();
                $bySetting = $this->indexBySetting($this->setup->templateStatuses($settings));

                self::assertTrue(
                    $bySetting['redirectTemplate']['exists'],
                    'A .twig template must be detected as present.',
                );

                if (in_array('html', Craft::$app->getConfig()->getGeneral()->defaultTemplateExtensions, true)) {
                    self::assertTrue(
                        $bySetting['expiredTemplate']['exists'],
                        'A .html template must be detected when html is a configured template extension.',
                    );
                }

                self::assertTrue(
                    $bySetting['qrTemplate']['exists'],
                    'A directory-style index.twig template must be detected as present.',
                );
            });
        } finally {
            FileHelper::removeDirectory($absDir);
        }
    }

    public function testTemplateStatusReportsMissingTemplate(): void
    {
        $dir = 'sl-test-setup-missing-' . bin2hex(random_bytes(4));

        $this->withSettings([
            'redirectTemplate' => $dir . '/redirect',
        ], function() use ($dir): void {
            $settings = ShortLinkManager::$plugin->getSettings();
            $bySetting = $this->indexBySetting($this->setup->templateStatuses($settings));

            self::assertFalse(
                $bySetting['redirectTemplate']['exists'],
                'A template with no matching file must be reported as missing.',
            );
        });
    }

    public function testIpSaltConfiguredWhenSaltPresent(): void
    {
        $this->withSettings([
            'ipHashSalt' => str_repeat('a', 40),
        ], function(): void {
            self::assertTrue(
                $this->setup->isIpSaltConfigured(ShortLinkManager::$plugin->getSettings()),
                'A real salt value must count as configured.',
            );
        });
    }

    public function testIpSaltNotConfiguredWhenEmpty(): void
    {
        $this->withSettings([
            'ipHashSalt' => '',
        ], function(): void {
            self::assertFalse(
                $this->setup->isIpSaltConfigured(ShortLinkManager::$plugin->getSettings()),
                'An empty salt must not count as configured.',
            );
        });
    }

    public function testIpSaltNotConfiguredForUnresolvedPlaceholder(): void
    {
        $this->withSettings([
            'ipHashSalt' => '$SHORTLINK_MANAGER_IP_SALT',
        ], function(): void {
            self::assertFalse(
                $this->setup->isIpSaltConfigured(ShortLinkManager::$plugin->getSettings()),
                'The unresolved default env placeholder must not count as configured.',
            );
        });
    }

    /**
     * @param array<int, array{setting: string, exists: bool}> $statuses
     * @return array<string, array{setting: string, exists: bool}>
     */
    private function indexBySetting(array $statuses): array
    {
        $indexed = [];
        foreach ($statuses as $status) {
            $indexed[$status['setting']] = $status;
        }

        return $indexed;
    }
}
