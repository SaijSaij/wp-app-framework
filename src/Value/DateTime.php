<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Value;

use Attribute;
use DateTimeImmutable;

/**
 * Marks a string as a date with a time of day, `2026-11-14T20:00`, or with `time: false` as a date, `2026-11-14`;
 * `''` means no date. The value is the local time of the site, without time zone: the time an editor chose, as it
 * is shown on the site. Its schema gets a `pattern`, so WordPress rejects other formats, and the value factory also
 * rejects dates that do not exist, such as `2026-02-30`. Editors choose it with the date picker of WordPress.
 * Values in this format sort and compare as text, so queries use the conditions of a string field
 * (`greaterOrEqual()`, `between()`) and sort by it.
 *
 * @api Attributes/Values
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class DateTime
{
    /**
     * The format of a date with time of day, for `DateTimeImmutable::createFromFormat()`.
     *
     * @internal
     */
    public const string FORMAT = 'Y-m-d\TH:i';

    /**
     * The format of a date without time of day.
     *
     * @internal
     */
    public const string DATE_FORMAT = 'Y-m-d';

    /**
     * The schema pattern of a date with time of day; the empty string means no date.
     *
     * @internal
     */
    public const string PATTERN = '^([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2})?$';

    /**
     * The schema pattern of a date without time of day.
     *
     * @internal
     */
    public const string DATE_PATTERN = '^([0-9]{4}-[0-9]{2}-[0-9]{2})?$';

    /**
     * @param bool $time With the time of day (hours and minutes); false for a date alone.
     */
    public function __construct(public bool $time = true) {}

    /**
     * Whether a value is a date (with time of day, if `$time`) that exists, or the empty string.
     *
     * @internal
     */
    public static function isDate(string $value, bool $time): bool
    {
        if ($value === '') {
            return true;
        }
        $format = $time ? self::FORMAT : self::DATE_FORMAT;
        // createFromFormat() rolls over (2026-02-30 becomes 2026-03-02); formatting it again shows the difference.
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);

        return $date !== false && $date->format($format) === $value;
    }
}
