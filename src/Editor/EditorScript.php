<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Editor;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Finds the editor script next to a class file during the build: Hero.php → Hero.editor.js.
 */
#[NoService]
final class EditorScript
{
    public const string SUFFIX = '.editor.js';

    /**
     * @param string|null $scriptDirectory Directory the consumer's build compiles scripts from (its `src/`);
     *                                     editor scripts must be below it. Null if the consumer has none.
     * @return array{string|null, string|null} The script as path below the script directory without extension
     *                                         (null without script), and an error message.
     */
    public static function locate(string $classFile, string $class, ?string $scriptDirectory): array
    {
        $script = dirname($classFile) . '/' . basename($classFile, '.php') . self::SUFFIX;
        if (!is_file($script)) {
            return [null, null];
        }
        if ($scriptDirectory === null || !str_starts_with($script, $scriptDirectory . '/')) {
            return [null, sprintf(
                'The editor script %s of %s is outside the script directory %s, which the build of the project compiles.',
                $script,
                $class,
                $scriptDirectory ?? '(none)',
            )];
        }

        return [substr($script, strlen($scriptDirectory) + 1, -strlen('.js')), null];
    }
}
