<?php
/**
 * LindemannRock ShortLink Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use lindemannrock\shortlinkmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * @since 5.21.1
 */
#[CoversNothing]
class SeomaticTrackingTemplateTest extends TestCase
{
    public function testSeomaticTrackingTemplateRendersDirectly(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__, 2) . '/src/templates/_integrations/seomatic.twig');

        $this->assertStringNotContainsString('{% macro', $template);
        $this->assertStringNotContainsString('{% endmacro %}', $template);
        $this->assertStringContainsString('code: link.code', $template);
        $this->assertStringContainsString('title: link.title', $template);
        $this->assertStringNotContainsString('shortLink.code', $template);
        $this->assertStringContainsString('window.dataLayer.push({{ eventDataJson|raw }});', $template);
        $this->assertStringContainsString('eventData|json_encode', $template);
    }
}
