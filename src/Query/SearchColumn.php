<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

/**
 * A column the search looks in.
 */
enum SearchColumn: string
{
    case Title = 'post_title';
    case Excerpt = 'post_excerpt';
    case Content = 'post_content';
}
