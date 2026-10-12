<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Asset;

/**
 * Scripts a consumer places next to a class: the editor script of a block or
 * block support (Hero.editor.js) and its front-end script (Hero.view.js).
 * The value is the file suffix.
 *
 * @internal
 */
enum ScriptKind: string
{
    case Editor = '.editor.js';
    case View = '.view.js';

    public function getLabel(): string
    {
        return match ($this) {
            self::Editor => 'editor script',
            self::View => 'view script',
        };
    }

    /**
     * Script handle of a block's script, e.g. my-theme-sections-hero-editor.
     *
     * @return non-empty-string
     */
    public function blockHandle(string $slug, string $block): string
    {
        return $slug . '-' . str_replace('/', '-', $block) . '-' . $this->handleSuffix();
    }

    /**
     * Script handle of a block support's script, e.g. my-theme-support-color-scheme-view for attribute colorScheme.
     *
     * @return non-empty-string
     */
    public function supportHandle(string $slug, string $attribute): string
    {
        return $slug . '-support-' . strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $attribute)) . '-' . $this->handleSuffix();
    }

    private function handleSuffix(): string
    {
        return match ($this) {
            self::Editor => 'editor',
            self::View => 'view',
        };
    }

    /**
     * Finds the script of this kind next to a class file during the build: Hero.php → Hero.editor.js.
     *
     * @param string|null $scriptDirectory Directory the consumer's build compiles scripts from (its `src/`);
     *                                     scripts must be below it. Null if the consumer has none.
     * @return array{string|null, string|null} The script as path below the script directory without extension
     *                                         (null without script), and an error message.
     */
    public function locate(string $classFile, string $class, ?string $scriptDirectory): array
    {
        $script = dirname($classFile) . '/' . basename($classFile, '.php') . $this->value;
        if (!is_file($script)) {
            return [null, null];
        }
        if ($scriptDirectory === null || !str_starts_with($script, $scriptDirectory . '/')) {
            return [null, sprintf(
                'The %s %s of %s is outside the script directory %s, which the build of the project compiles.',
                $this->getLabel(),
                $script,
                $class,
                $scriptDirectory ?? '(none)',
            )];
        }

        return [substr($script, strlen($scriptDirectory) + 1, -strlen('.js')), null];
    }
}
