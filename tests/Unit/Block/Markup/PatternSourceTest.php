<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Block\Markup;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Block\Markup\BlockMarkupParser;
use WebCodingCat\WpAppFramework\Block\Markup\PatternSource;

final class PatternSourceTest extends TestCase
{
    public function testReplacesPhpCodeAndKeepsLines(): void
    {
        $marker = BlockMarkupParser::PHP_MARKER;
        $code = "<?php\n/**\n * Title: Probe\n */\n?>\n<p><?= 'a' ?></p>\n<?php echo 'b';\n";

        self::assertSame(
            // The closing tag swallows one line break; it is kept as well.
            $marker . "\n\n\n\n\n" . '<p>' . $marker . "</p>\n" . $marker . "\n",
            PatternSource::toMarkup($code),
        );
        self::assertSame(substr_count($code, "\n"), substr_count(PatternSource::toMarkup($code), "\n"));
    }

    public function testPlainHtmlStaysUnchanged(): void
    {
        self::assertSame('<!-- wp:paragraph /-->', PatternSource::toMarkup('<!-- wp:paragraph /-->'));
    }
}
