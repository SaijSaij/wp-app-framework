<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Value;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Filters the rich text of a value for users without `unfiltered_html`, like
 * WordPress filters block attributes when saving (filter_block_kses()).
 *
 * @internal
 */
#[NoService]
final class HtmlFilter
{
    /**
     * Filters the rich text at the given paths ("footer", "links.*.text"; `*` for list items) with wp_kses_post(),
     * unless the current user may post unfiltered HTML.
     *
     * @param list<string> $paths
     */
    public static function filter(mixed $value, array $paths): mixed
    {
        if ($paths === [] || current_user_can('unfiltered_html')) {
            return $value;
        }
        foreach ($paths as $path) {
            $value = self::filterPath($value, $path === '' ? [] : explode('.', $path));
        }

        return $value;
    }

    /**
     * @param list<string> $path
     */
    private static function filterPath(mixed $value, array $path): mixed
    {
        if ($path === []) {
            return is_string($value) ? wp_kses_post($value) : $value;
        }
        if (!is_array($value)) {
            return $value;
        }
        $segment = array_shift($path);
        foreach ($value as $key => $item) {
            if ($segment === '*' ? is_int($key) : $key === $segment) {
                $value[$key] = self::filterPath($item, $path);
            }
        }

        return $value;
    }
}
