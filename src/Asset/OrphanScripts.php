<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Asset;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\Support\BlockSupport;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Setting\SettingsGroup;
use SplFileInfo;

/**
 * Finds editor and view scripts in a consumer's `src/` that belong to no block
 * or block support, and settings scripts that belong to no settings group. The
 * webpack helper builds every such file, but the framework loads a script in
 * `src/` only next to the class it belongs to (Hero.php → Hero.view.js,
 * Design.php → Design.settings.js); any other one would be built and never
 * loaded. Editor and view scripts without a class belong in `assets/`.
 *
 * @internal
 */
#[NoService]
final class OrphanScripts
{
    /**
     * @param array<class-string, ReflectionClass<object>> $classes         Loaded classes of the build.
     * @param string|null                                  $scriptDirectory The consumer's `src/`; null without one.
     * @return list<string> One error per script without the class it belongs to next to it.
     */
    public static function errors(array $classes, ?string $scriptDirectory): array
    {
        if ($scriptDirectory === null || !is_dir($scriptDirectory)) {
            return [];
        }

        $owners = [];
        $groups = [];
        foreach ($classes as $class) {
            if ($class->getAttributes(Block::class) !== [] || $class->getAttributes(BlockSupport::class) !== []) {
                $owners[(string) realpath((string) $class->getFileName())] = true;
            }
            if ($class->getAttributes(SettingsGroup::class) !== []) {
                $groups[(string) realpath((string) $class->getFileName())] = true;
            }
        }

        $errors = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scriptDirectory, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            foreach (ScriptKind::cases() as $kind) {
                $name = $file->getFilename();
                if (!$file->isFile() || !str_ends_with($name, $kind->value)) {
                    continue;
                }
                $classFile = (string) realpath($file->getPath() . '/' . substr($name, 0, -strlen($kind->value)) . '.php');
                if ($kind === ScriptKind::Settings) {
                    if (!isset($groups[$classFile])) {
                        $errors[] = sprintf(
                            'The settings script %s belongs to no settings group: a settings script is loaded only next to the class of a #[SettingsGroup] (Design.php → Design.settings.js).',
                            $file->getPathname(),
                        );
                    }
                    continue;
                }
                if (!isset($owners[$classFile])) {
                    $errors[] = sprintf(
                        'The %s %s belongs to no block or block support: a script in %s is loaded only next to the class of a block or block support (%s). Move a script without a block to %s/ in the root of the theme or plugin.',
                        $kind->getLabel(),
                        $file->getPathname(),
                        $scriptDirectory,
                        'Hero.php → Hero' . $kind->value,
                        AssetDirectory::NAME,
                    );
                }
            }
        }
        sort($errors);

        return $errors;
    }
}
