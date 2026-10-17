<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Twig;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Finds templates of one level that override templates of a lower level, so
 * the build can report them: an override is allowed but must not go unnoticed.
 */
#[NoService]
final class TemplateOverrides
{
    /**
     * @param string $upper Template directory of the higher level, e.g. the theme's `src/`.
     * @param string $lower Template directory of the lower level, e.g. the framework's `src/`.
     * @return list<string> Template names (paths relative to the directories) present in both, sorted.
     */
    public static function find(string $upper, string $lower): array
    {
        return array_values(array_intersect(self::templates($upper), self::templates($lower)));
    }

    /**
     * @return list<string> Sorted template names; empty if the directory does not exist.
     */
    private static function templates(string $directory): array
    {
        return is_dir($directory) ? TemplateFiles::names($directory) : [];
    }
}
