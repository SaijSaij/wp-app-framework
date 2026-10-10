<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Twig;

use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Version of the templates a release build ships, written into its manifest.
 *
 * Release mode compiles templates without auto reload, so Twig never notices a
 * changed template; the cache directory of a release is named after this
 * version instead. It changes whenever a template is added, removed, renamed or
 * edited, and does not depend on where the directories are located.
 */
#[NoService]
final class TemplateVersion
{
    /**
     * @param list<string> $directories Template directories in search order (the theme's `src/`, the framework's `src/`).
     *                                  Each must exist.
     * @return non-empty-string 16 hexadecimal characters.
     */
    public static function compute(array $directories): string
    {
        $context = hash_init('sha256');

        foreach ($directories as $index => $directory) {
            if (!is_dir($directory)) {
                throw new RuntimeException(sprintf('Template directory %s does not exist.', $directory));
            }

            foreach (TemplateFiles::names($directory) as $name) {
                $contents = file_get_contents($directory . '/' . $name);
                if ($contents === false) {
                    throw new RuntimeException(sprintf('Cannot read template %s/%s.', $directory, $name));
                }
                // The index keeps the level of a template: moving it to another level changes the version.
                hash_update($context, $index . "\0" . $name . "\0" . strlen($contents) . "\0" . $contents);
            }
        }

        return substr(hash_final($context), 0, 16);
    }
}
