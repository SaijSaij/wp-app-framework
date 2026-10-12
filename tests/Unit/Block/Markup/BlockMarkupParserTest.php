<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Block\Markup;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Block\Markup\BlockMarkupParser;
use Saij\WpAppFramework\Block\Markup\ParsedBlock;

final class BlockMarkupParserTest extends TestCase
{
    public function testParsesNestedAndVoidBlocksWithLines(): void
    {
        $parser = new BlockMarkupParser(<<<'HTML'
            <p>Freeform</p>
            <!-- wp:fixture/section {"a":1} -->
            <div>
            <!-- wp:paragraph -->
            <p>Text</p>
            <!-- /wp:paragraph -->
            <!-- wp:fixture/card {"title":"}"} /-->
            </div>
            <!-- /wp:fixture/section -->
            HTML);

        self::assertSame([], $parser->getErrors());
        [$section] = $parser->getBlocks();
        self::assertSame('fixture/section', $section->getName());
        self::assertSame(['a' => 1], $section->getAttributes());
        self::assertSame(2, $section->getLine());
        // Inner HTML without the inner blocks, as WordPress keeps it in innerHTML.
        self::assertSame("\n<div>\n\n\n</div>\n", $section->getInnerHtml());

        [$paragraph, $card] = $section->getInnerBlocks();
        self::assertSame(['core/paragraph', 4, [], "\n<p>Text</p>\n"], [$paragraph->getName(), $paragraph->getLine(), $paragraph->getAttributes(), $paragraph->getInnerHtml()]);
        self::assertSame(['fixture/card', 7, ['title' => '}']], [$card->getName(), $card->getLine(), $card->getAttributes()]);
    }

    public function testRecognizesHtmlOutsideOfBlocks(): void
    {
        self::assertFalse(new BlockMarkupParser("\n<!-- wp:a/b -->\n<p>Inside</p>\n<!-- /wp:a/b -->\n" . BlockMarkupParser::PHP_MARKER . "\n")->hasFreeformHtml());
        self::assertTrue(new BlockMarkupParser("<p>Outside</p>\n<!-- wp:a/b /-->")->hasFreeformHtml());
    }

    public function testInvalidJsonGivesNullAttributes(): void
    {
        $blocks = new BlockMarkupParser('<!-- wp:fixture/card {"title":} /-->')->getBlocks();

        self::assertNull($blocks[0]->getAttributes());
    }

    public function testRecognizesPhpMarkerInTheComment(): void
    {
        $blocks = new BlockMarkupParser('<!-- wp:fixture/card {"title":"' . BlockMarkupParser::PHP_MARKER . '"} /--><!-- wp:fixture/card /-->')->getBlocks();

        self::assertSame([true, false], array_map(static fn(ParsedBlock $block): bool => $block->hasPhp(), $blocks));
    }

    public function testReportsWhatWordPressRepairsSilently(): void
    {
        $parser = new BlockMarkupParser(<<<'HTML'
            <!-- /wp:fixture/card -->
            <!-- wp:fixture/a -->
            <!-- wp:fixture/b -->
            <!-- /wp:fixture/a -->
            <!--wp:fixture/c /-->
            <!-- wp:fixture/d -->
            HTML);

        self::assertSame(
            [
                [1, 'closing comment of block fixture/card without an opening comment.'],
                [4, 'closing comment of block fixture/a, but block fixture/b (line 3) is open.'],
                [5, 'malformed block comment; WordPress treats it as HTML.'],
                [6, 'block fixture/d is not closed.'],
                [2, 'block fixture/a is not closed.'],
            ],
            $parser->getErrors(),
        );
        // Like WordPress, a closer closes the innermost open block.
        self::assertSame(['fixture/a'], array_map(static fn(ParsedBlock $block): string => $block->getName(), $parser->getBlocks()));
    }
}
