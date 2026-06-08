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
        self::assertTrue($this->isValidDestinationUrl('/relative/path'));
    }

    public function testRejectsBareWordsAndOtherSchemes(): void
    {
        // Pre-existing allowlist behavior: only http(s)/relative are accepted.
        self::assertFalse($this->isValidDestinationUrl('mailto:x@y.com'));
        self::assertFalse($this->isValidDestinationUrl('not a url'));
        self::assertFalse($this->isValidDestinationUrl(''));
    }
}
