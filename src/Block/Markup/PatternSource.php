<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Block\Markup;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Turns a pattern file into the block markup the build can check. WordPress includes pattern files and uses
 * their output; the build cannot run them. It keeps the HTML outside of PHP tags and replaces every piece of
 * PHP code, including the header comment, with BlockMarkupParser::PHP_MARKER, keeping its line breaks so that
 * line numbers match the file.
 */
#[NoService]
final class PatternSource
{
    public static function toMarkup(string $code): string
    {
        $markup = '';
        $php = null;

        foreach (token_get_all($code) as $token) {
            [$id, $text] = is_array($token) ? [$token[0], $token[1]] : [null, $token];

            if ($id === T_INLINE_HTML) {
                $markup .= $text;
                continue;
            }

            $php ??= '';
            $php .= $text;
            if ($id === T_CLOSE_TAG) {
                $markup .= self::marker($php);
                $php = null;
            }
        }

        // A file may end in PHP code without a closing tag.
        if ($php !== null) {
            $markup .= self::marker($php);
        }

        return $markup;
    }

    private static function marker(string $php): string
    {
        return BlockMarkupParser::PHP_MARKER . str_repeat("\n", substr_count($php, "\n"));
    }
}
