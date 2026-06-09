<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\shortlinkmanager\controllers\ImportExportController;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Import destination-URL validation must reject executable schemes on top of its
 * http(s)/relative allowlist.
 *
 * @since 5.20.3
 */
#[CoversClass(ImportExportController::class)]
final class ImportUrlValidationTest extends TestCase
{
    private function isValidDestinationUrl(string $value): bool
    {
        $controller = new ImportExportController('import-export', ShortLinkManager::$plugin);
        $method = new \ReflectionMethod($controller, 'isValidDestinationUrl');

        return (bool) $method->invoke($controller, $value);
    }

    public function testRejectsExecutableSchemes(): void
    {
        self::assertFalse($this->isValidDestinationUrl('javascript:alert(1)'));
        self::assertFalse($this->isValidDestinationUrl('javascript://%0aalert(1)'));
        self::assertFalse($this->isValidDestinationUrl('data:text/html,<script>alert(1)</script>'));
        self::assertFalse($this->isValidDestinationUrl('vbscript:msgbox(1)'));
        self::assertFalse($this->isValidDestinationUrl('file:///etc/passwd'));
        self::assertFalse($this->isValidDestinationUrl("java\tscript:alert(1)"));
    }

    public function testAcceptsHttpAndRelativeUrls(): void
    {
        self::assertTrue($this->isValidDestinationUrl('https://example.com'));
        self::assertTrue($this->isValidDestinationUrl('http://example.com/path'));
        // Single-slash relative paths stay valid.
        self::assertTrue($this->isValidDestinationUrl('/relative/path'));
    }

    public function testRejectsProtocolRelativeHost(): void
    {
        // `//host` resolves off-site; the runtime already blocks it, so import
        // must reject it too rather than store a destination that won't be honored.
        self::assertFalse($this->isValidDestinationUrl('//evil.com'));
        self::assertFalse($this->isValidDestinationUrl('//evil.com/phishing'));
    }

    public function testRejectsBareWordsAndOtherSchemes(): void
    {
        // Allowlist: only http(s) / single-slash relative are accepted.
        self::assertFalse($this->isValidDestinationUrl('mailto:x@y.com'));
        self::assertFalse($this->isValidDestinationUrl('not a url'));
        self::assertFalse($this->isValidDestinationUrl(''));
    }
}
