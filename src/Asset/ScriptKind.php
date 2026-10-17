<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Asset;

/**
 * Scripts a consumer places next to a class: the editor script of a block or
 * block support (Hero.editor.js), its front-end script (Hero.view.js) and the
 * settings script of a settings group (Design.settings.js), which runs on the
 * settings page. The value is the file suffix.
 *
 * @internal
 */
enum ScriptKind: string
{
    case Editor = '.editor.js';
    case View = '.view.js';
    case Settings = '.settings.js';

    public function getLabel(): string
    {
        return match ($this) {
            self::Editor => 'editor script',
            self::View => 'view script',
            self::Settings => 'settings script',
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

    /**
     * Script handle of a settings group's script, e.g. my-theme-settings-color-schemes for the group color_schemes.
     *
     * @return non-empty-string
     */
    public static function settingsHandle(string $slug, string $group): string
    {
        return $slug . '-settings-' . self::kebab($group);
    }

    /**
     * Script handle of a script in the asset directory, e.g. my-theme-document-nav-menu-view for
     * assets/nav/menu.view.js; the same for document views and editor scripts without a block.
     *
     * @param string $script Below the build directory, without extension, e.g. assets/nav/menu.view.
     * @return non-empty-string
     */
    public function documentHandle(string $slug, string $script): string
    {
        $name = substr($script, strlen(AssetDirectory::NAME) + 1, -strlen($this->value) + strlen('.js'));

        return $slug . '-document-' . self::kebab($name) . '-' . $this->handleSuffix();
    }

    /**
     * Script handle of a shared module, e.g. my-theme-shared-ui-modal for assets/shared/ui/Modal.js; the webpack
     * helper of the framework derives the same handle for imports of `#shared/ui/Modal`.
     *
     * @return non-empty-string
     */
    public static function sharedHandle(string $slug, string $module): string
    {
        return $slug . '-shared-' . self::kebab($module);
    }

    /**
     * A name as part of a script handle, e.g. ui/ModalStack → ui-modal-stack (as kebab() in webpack.config.js).
     */
    public static function kebab(string $name): string
    {
        $kebab = strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $name));

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $kebab), '-');
    }

    private function handleSuffix(): string
    {
        return match ($this) {
            self::Editor => 'editor',
            self::View => 'view',
            self::Settings => 'settings',
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
