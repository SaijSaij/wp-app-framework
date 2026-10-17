<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Value;

use Attribute;

/**
 * Marks a string as color: a hex color with optional alpha (`#rgb`, `#rgba`,
 * `#rrggbb` or `#rrggbbaa`), or `''` for no color. Its schema gets a `pattern`,
 * so WordPress rejects anything else, and the value factory checks the value
 * again; a template can print it into a `style` attribute. Editors choose it
 * with a color picker that offers the colors of the theme's palette.
 *
 * @api Attributes/Values
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Color
{
    /**
     * The schema pattern of a color; the empty string means no color.
     *
     * @internal
     */
    public const string PATTERN = '^(#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8}))?$';

    /**
     * Whether a value is a color or the empty string. Stricter than the schema: WordPress matches the pattern
     * without the modifier D, so its `$` also accepts a newline at the end.
     *
     * @internal
     */
    public static function isColor(string $value): bool
    {
        return preg_match('~' . self::PATTERN . '~D', $value) === 1;
    }
}
