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
use lindemannrock\shortlinkmanager\models\Settings;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.21.0
 */
#[CoversClass(Settings::class)]
final class SettingsValidationTest extends TestCase
{
    public function testTemplateEnvironmentExpressionsStayRawAfterValidationAndResolveOnConsumption(): void
    {
        $relativeDirectory = 'sl-test-env-validation-' . bin2hex(random_bytes(4));
        $templateDirectory = Craft::$app->getPath()->getSiteTemplatesPath()
            . DIRECTORY_SEPARATOR . $relativeDirectory;
        FileHelper::createDirectory($templateDirectory);
        $environment = [
            'SHORTLINK_MANAGER_TEST_VALID_REDIRECT_TEMPLATE' => $relativeDirectory . '/redirect',
            'SHORTLINK_MANAGER_TEST_VALID_EXPIRED_TEMPLATE' => $relativeDirectory . '/expired',
            'SHORTLINK_MANAGER_TEST_VALID_QR_TEMPLATE' => $relativeDirectory . '/qr',
        ];
        $original = [];
        foreach ($environment as $name => $value) {
            $original[$name] = [array_key_exists($name, $_SERVER), $_SERVER[$name] ?? null];
            $_SERVER[$name] = $value;
            self::assertNotFalse(file_put_contents(
                $templateDirectory . DIRECTORY_SEPARATOR . basename($value) . '.twig',
                $name,
            ));
        }

        try {
            $settings = new Settings();
            $settings->redirectTemplate = '$SHORTLINK_MANAGER_TEST_VALID_REDIRECT_TEMPLATE';
            $settings->expiredTemplate = '$SHORTLINK_MANAGER_TEST_VALID_EXPIRED_TEMPLATE';
            $settings->qrTemplate = '$SHORTLINK_MANAGER_TEST_VALID_QR_TEMPLATE';

            self::assertTrue(
                $settings->validate(['redirectTemplate', 'expiredTemplate', 'qrTemplate']),
                json_encode($settings->getErrors()),
            );
            self::assertSame('$SHORTLINK_MANAGER_TEST_VALID_REDIRECT_TEMPLATE', $settings->redirectTemplate);
            self::assertSame('$SHORTLINK_MANAGER_TEST_VALID_EXPIRED_TEMPLATE', $settings->expiredTemplate);
            self::assertSame('$SHORTLINK_MANAGER_TEST_VALID_QR_TEMPLATE', $settings->qrTemplate);
            self::assertSame($relativeDirectory . '/redirect', $settings->getResolvedRedirectTemplate());
            self::assertSame($relativeDirectory . '/expired', $settings->getResolvedExpiredTemplate());
            self::assertSame($relativeDirectory . '/qr', $settings->getResolvedQrTemplate());
        } finally {
            foreach ($original as $name => [$existed, $value]) {
                if ($existed) {
                    $_SERVER[$name] = $value;
                } else {
                    unset($_SERVER[$name]);
                }
            }
            FileHelper::removeDirectory($templateDirectory);
        }
    }

    public function testResolvedTemplateContractPreservesDefaultsLiteralsExtensionsAndMissingValues(): void
    {
        $settings = new Settings();
        self::assertSame('shortlink-manager/redirect', $settings->getResolvedRedirectTemplate());
        self::assertSame('shortlink-manager/expired', $settings->getResolvedExpiredTemplate());
        self::assertSame('shortlink-manager/qr', $settings->getResolvedQrTemplate());

        $settings->redirectTemplate = '/custom/redirect.html/';
        $settings->expiredTemplate = 'custom/expired.json';
        $settings->qrTemplate = 'custom/qr';
        self::assertSame('custom/redirect.html', $settings->getResolvedRedirectTemplate());
        self::assertSame('custom/expired.json', $settings->getResolvedExpiredTemplate());
        self::assertSame('custom/qr', $settings->getResolvedQrTemplate());

        $missingEnvironmentName = 'SHORTLINK_MANAGER_TEST_UNRESOLVABLE_TEMPLATE';
        $existed = array_key_exists($missingEnvironmentName, $_SERVER);
        $value = $existed ? $_SERVER[$missingEnvironmentName] : null;
        unset($_SERVER[$missingEnvironmentName]);
        try {
            $settings->redirectTemplate = '$' . $missingEnvironmentName;
            self::assertSame('', $settings->getResolvedRedirectTemplate());
            self::assertSame('$' . $missingEnvironmentName, $settings->redirectTemplate);
        } finally {
            if ($existed) {
                $_SERVER[$missingEnvironmentName] = $value;
            }
        }
    }

    public function testQrDownloadFilenameRejectsSlugToken(): void
    {
        $settings = new Settings();
        $settings->qrDownloadFilename = '{slug}-qr-{size}';

        self::assertFalse($settings->validate(['qrDownloadFilename']));
        self::assertNotEmpty($settings->getErrors('qrDownloadFilename'));
    }

    public function testQrDownloadFilenameAcceptsSupportedTokens(): void
    {
        $settings = new Settings();
        $settings->qrDownloadFilename = '{code}-qr-{size}.{format}';

        self::assertTrue($settings->validate(['qrDownloadFilename']), json_encode($settings->getErrors()));
    }
}
