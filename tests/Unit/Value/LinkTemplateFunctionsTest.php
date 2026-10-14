<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Value;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Value\Link;
use Saij\WpAppFramework\Value\LinkKind;
use Saij\WpAppFramework\Value\LinkTemplateFunctions;

final class LinkTemplateFunctionsTest extends TestCase
{
    public function testLinkAttributesPrintTheEscapedUrlAndTheNewTab(): void
    {
        $functions = new LinkTemplateFunctions();

        self::assertSame('href="https://example.org/about/"', $functions->linkAttributes(new Link('https://example.org/about/')));
        self::assertSame(
            'href="https://example.org/?a=1&#038;b=2" target="_blank" rel="noopener"',
            $functions->linkAttributes(new Link('https://example.org/?a=1&b=2', true, LinkKind::Post, 4)),
        );
        // Quotes cannot leave the attribute.
        self::assertSame('href="https://example.org/onclick=x"', $functions->linkAttributes(new Link('https://example.org/" onclick="x')));
    }

    public function testLinksWithoutUsableUrlPrintNothing(): void
    {
        $functions = new LinkTemplateFunctions();

        self::assertSame('', $functions->linkAttributes(new Link()));
        self::assertSame('', $functions->linkAttributes(new Link('', true, LinkKind::Post, 3)));
        self::assertSame('', $functions->linkAttributes(new Link('javascript:alert(1)', true)));
    }
}
