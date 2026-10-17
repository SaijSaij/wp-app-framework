<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Query;

/**
 * Post data to sort by; meta fields sort through their field (`EventDetailsFields::capacity()`).
 *
 * @api Enums
 */
enum PostOrder: string
{
    case Date = 'date';
    case Modified = 'modified';
    case Title = 'title';
    case MenuOrder = 'menu_order';
    case Id = 'ID';
    case Slug = 'name';
    /** A random order; not with cache(). */
    case Random = 'rand';

    /**
     * @internal The column in the posts table (sorting next to meta fields).
     */
    public function column(): string
    {
        return match ($this) {
            self::Date => 'post_date',
            self::Modified => 'post_modified',
            self::Title => 'post_title',
            self::MenuOrder => 'menu_order',
            self::Id => 'ID',
            self::Slug => 'post_name',
            self::Random => 'RAND()',
        };
    }
}
