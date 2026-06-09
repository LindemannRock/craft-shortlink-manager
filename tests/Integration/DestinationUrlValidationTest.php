<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\shortlinkmanager\elements\ShortLink;
use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The element must accept http(s) URLs and single-slash relative paths as a
 * destination, but reject protocol-relative `//host` — matching both the import
 * validator and the runtime redirect guard.
 *
 * @since 5.20.3
 */
#[CoversClass(ShortLink::class)]
final class DestinationUrlValidationTest extends TestCase
{
    private function validatesDestination(string $url): bool
    {
        $element = new ShortLink();
        $element->destinationUrl = $url;

        return $element->validate(['destinationUrl']);
    }

    public function testAcceptsHttpAndRelative(): void
    {
        self::assertTrue($this->validatesDestination('https://example.com'));
        self::assertTrue($this->validatesDestination('http://example.com/path'));
        self::assertTrue($this->validatesDestination('/page'));
    }

    public function testRejectsProtocolRelativeHost(): void
    {
        self::assertFalse($this->validatesDestination('//evil.com'));
        self::assertFalse($this->validatesDestination('//evil.com/phishing'));
    }

    public function testRejectsOtherSchemes(): void
    {
        self::assertFalse($this->validatesDestination('mailto:x@y.com'));
        self::assertFalse($this->validatesDestination('javascript:alert(1)'));
    }
}
