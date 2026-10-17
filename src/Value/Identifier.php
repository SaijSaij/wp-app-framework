<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Value;

use Attribute;

/**
 * Marks a string of a list item as its identifier: eight lowercase hex digits, generated once when the item is added
 * and never edited, unique within the list. Other values refer to an item by it, so moving or renaming the item keeps
 * the reference. It has no control. The list editors generate it when they add an item; settings groups also fill
 * in missing and duplicate identifiers when they are saved. The default is `''`, the value of an item without
 * identifier yet.
 *
 * @api Attributes/Values
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Identifier
{
    /**
     * The schema pattern of an identifier; the empty string means no identifier yet.
     *
     * @internal
     */
    public const string PATTERN = '^([0-9a-f]{8})?$';

    /**
     * Whether a value is an identifier or the empty string. Stricter than the schema: WordPress matches the pattern
     * without the modifier D, so its `$` also accepts a newline at the end.
     *
     * @internal
     */
    public static function isIdentifier(string $value): bool
    {
        return preg_match('~' . self::PATTERN . '~D', $value) === 1;
    }

    /**
     * A new identifier that is not among the given ones.
     *
     * @param list<string> $taken
     * @internal
     */
    public static function generate(array $taken): string
    {
        do {
            $identifier = bin2hex(random_bytes(4));
        } while (in_array($identifier, $taken, true));

        return $identifier;
    }
}
