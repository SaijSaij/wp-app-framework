<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Project;

use JsonException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\Discovery\Source;
use Saij\WpAppFramework\Release\FileHeader;

/**
 * What the build reads from a consumer's composer.json: its kind (`type`; for
 * themes also the `Template:` header of style.css, which makes a child theme),
 * its PSR-4 directories and the plugins whose public template functions and
 * filters its templates call (`extra.wp-app-framework.template-plugins`). A child
 * theme uses those of its parent theme without declaring them.
 *
 * The slug is the name of the project directory, as WordPress names themes and
 * plugins after their directory; technical names derive from it. The namespace
 * (`extra.wp-app-framework.namespace`) names what content and data refer to:
 * blocks, REST routes and settings options. It must not depend on the directory
 * name, which changes when a theme or plugin is installed under another name.
 */
#[NoService]
final readonly class ComposerProject
{
    /** Allowed slugs: the directory names WordPress and the webpack helper accept for framework instances. */
    public const string SLUG_PATTERN = '/^[a-z0-9_-]+$/';

    /** Allowed namespaces: valid as block namespace (WP_Block_Type_Registry) and REST namespace. */
    public const string NAMESPACE_PATTERN = '/^[a-z][a-z0-9-]*$/';

    /** Namespaces of WordPress (blocks `core/…`, REST `wp/…`) and of the framework's own REST routes. */
    public const array RESERVED_NAMESPACES = ['core', 'wp', 'wp-app-framework'];

    /**
     * @param non-empty-string      $directory       Real path of the project.
     * @param non-empty-string      $slug
     * @param non-empty-string      $namespace       `extra.wp-app-framework.namespace`.
     * @param list<Source>          $sources         PSR-4 directories.
     * @param non-empty-string|null  $parent          Slug of the parent theme of a child theme.
     * @param list<non-empty-string> $templateProviders Slugs of the plugins and the parent theme whose public template callables
     *                                                  the templates use.
     * @param list<non-empty-string> $foreignNamespaces Namespaces prefixed in a release (dependencies), with a trailing backslash.
     */
    private function __construct(
        private string $directory,
        private string $composerFile,
        private string $slug,
        private string $namespace,
        private ProjectKind $kind,
        private ?string $parent,
        private array $sources,
        private array $templateProviders,
        private array $foreignNamespaces,
    ) {}

    public static function load(string $directory): self
    {
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) {
            throw new BuildException([sprintf('Project directory %s does not exist.', $directory)]);
        }

        $slug = basename($root);
        if ($slug === '' || preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            throw new BuildException([sprintf(
                'The directory name "%s" of %s is not a valid slug; WordPress names the theme or plugin after it. Use lowercase letters, digits, "-" and "_".',
                $slug,
                $root,
            )]);
        }

        $composerFile = $root . '/composer.json';
        if (!is_file($composerFile)) {
            throw new BuildException([sprintf('%s not found; the build reads the type and the PSR-4 directories of the project from it.', $composerFile)]);
        }

        try {
            $composer = json_decode((string) file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new BuildException([sprintf('%s is not valid JSON: %s', $composerFile, $exception->getMessage())]);
        }
        if (!is_array($composer)) {
            throw new BuildException([sprintf('%s does not contain a JSON object.', $composerFile)]);
        }

        $errors = [];
        [$kind, $parent] = self::kind($root, $composerFile, $composer, $slug, $errors);
        $sources = self::sources($root, $composerFile, $composer, $errors);
        $templateProviders = self::templateProviders($composerFile, $composer, $slug, $parent, $errors);
        $namespace = self::projectNamespace($composerFile, $composer, $errors);

        if ($errors !== [] || $kind === null || $namespace === null) {
            throw new BuildException($errors === [] ? [sprintf('%s is invalid.', $composerFile)] : $errors);
        }

        return new self($root, $composerFile, $slug, $namespace, $kind, $parent, $sources, $templateProviders, self::foreignNamespaces($root, $composer));
    }

    /**
     * @return non-empty-string
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function getComposerFile(): string
    {
        return $this->composerFile;
    }

    /**
     * @return non-empty-string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * The namespace of blocks, REST routes and settings options (`extra.wp-app-framework.namespace`).
     *
     * @return non-empty-string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getKind(): ProjectKind
    {
        return $this->kind;
    }

    /**
     * The file with the headers of the theme (style.css) or plugin ({slug}.php).
     *
     * @return non-empty-string
     */
    public function getHeaderFile(): string
    {
        return $this->directory . '/' . $this->kind->getHeaderFile($this->slug);
    }

    /**
     * The name of the theme or plugin (`Theme Name:` or `Plugin Name:` header), null if the header file or the header
     * is missing.
     *
     * @return non-empty-string|null
     */
    public function getName(): ?string
    {
        $file = $this->getHeaderFile();

        return is_file($file) ? FileHeader::value($file, $this->kind->getNameHeader()) : null;
    }

    /**
     * Slug of the parent theme (`Template:` header), for child themes only.
     *
     * @return non-empty-string|null
     */
    public function getParent(): ?string
    {
        return $this->parent;
    }

    /**
     * @return list<Source>
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * Slugs of the plugins (`extra.wp-app-framework.template-plugins`) and of the
     * parent theme of a child theme, whose public template callables the templates use.
     *
     * @return list<non-empty-string>
     */
    public function getTemplateProviders(): array
    {
        return $this->templateProviders;
    }

    /**
     * @return list<non-empty-string>
     */
    public function getForeignNamespaces(): array
    {
        return $this->foreignNamespaces;
    }

    /**
     * Prefix of the public template functions and filters of a plugin or theme: the slug
     * with underscores instead of hyphens, followed by an underscore
     * (`plugin-dev-test` → `plugin_dev_test_`).
     *
     * @param non-empty-string $slug
     * @return non-empty-string
     */
    public static function templatePrefix(string $slug): string
    {
        return str_replace('-', '_', $slug) . '_';
    }

    /**
     * Prefix of the data names of a namespace – settings options, post types, taxonomies and meta keys: the
     * namespace with underscores instead of hyphens, followed by an underscore (`dev-test` → `dev_test_`).
     *
     * @param non-empty-string $namespace
     * @return non-empty-string
     */
    public static function dataPrefix(string $namespace): string
    {
        return str_replace('-', '_', $namespace) . '_';
    }

    /**
     * The validation error of a namespace, null if it is valid.
     */
    public static function namespaceError(string $namespace): ?string
    {
        if (preg_match(self::NAMESPACE_PATTERN, $namespace) !== 1) {
            return sprintf('"%s" is not a valid namespace: start with a lowercase letter, then use lowercase letters, digits and "-".', $namespace);
        }
        if (in_array($namespace, self::RESERVED_NAMESPACES, true)) {
            return sprintf('the namespace "%s" is reserved (%s).', $namespace, implode(', ', self::RESERVED_NAMESPACES));
        }

        return null;
    }

    /**
     * @param array<mixed> $composer
     * @param list<string> $errors
     * @return non-empty-string|null
     */
    private static function projectNamespace(string $composerFile, array $composer, array &$errors): ?string
    {
        $extra = is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
        $settings = $extra['wp-app-framework'] ?? [];
        // templateProviders() reports a value that is not an object.
        if (!is_array($settings)) {
            return null;
        }
        $namespace = $settings['namespace'] ?? null;
        if ($namespace === null) {
            $errors[] = sprintf(
                '%s declares no extra.wp-app-framework.namespace; the namespace names the blocks, REST routes and settings options of the theme or plugin, e.g. "sections" for blocks "sections/hero".',
                $composerFile,
            );

            return null;
        }
        if (!is_string($namespace) || $namespace === '') {
            $errors[] = sprintf('extra.wp-app-framework.namespace in %s must be a non-empty string, found %s.', $composerFile, json_encode($namespace));

            return null;
        }
        $error = self::namespaceError($namespace);
        if ($error !== null) {
            $errors[] = sprintf('extra.wp-app-framework.namespace in %s: %s', $composerFile, $error);

            return null;
        }

        return $namespace;
    }

    /**
     * The kind from the composer.json `type`; a theme whose style.css declares a
     * parent (`Template:`) is a child theme.
     *
     * @param array<mixed>     $composer
     * @param non-empty-string $slug
     * @param list<string>     $errors
     * @return array{ProjectKind|null, non-empty-string|null}
     */
    private static function kind(string $root, string $composerFile, array $composer, string $slug, array &$errors): array
    {
        $type = $composer['type'] ?? null;
        if ($type === ProjectKind::Plugin->getComposerType()) {
            return [ProjectKind::Plugin, null];
        }
        if ($type !== ProjectKind::Theme->getComposerType()) {
            $errors[] = sprintf(
                '%s must declare "type": "%s" or "%s", found %s.',
                $composerFile,
                ProjectKind::Theme->getComposerType(),
                ProjectKind::Plugin->getComposerType(),
                json_encode($type),
            );

            return [null, null];
        }

        $styleFile = $root . '/style.css';
        if (!is_file($styleFile)) {
            $errors[] = sprintf('%s not found; %s declares "type": "%s", and a theme needs a style.css.', $styleFile, $composerFile, $type);

            return [null, null];
        }

        $parent = FileHeader::value($styleFile, 'Template');
        if ($parent === null) {
            return [ProjectKind::Theme, null];
        }
        if (preg_match(self::SLUG_PATTERN, $parent) !== 1 || $parent === $slug) {
            $errors[] = sprintf(
                'The "Template:" header of %s names "%s", which is not the directory name of another theme; a child theme names its parent theme there.',
                $styleFile,
                $parent,
            );

            return [null, null];
        }

        return [ProjectKind::ChildTheme, $parent];
    }

    /**
     * @param array<mixed> $composer
     * @param list<string> $errors
     * @return list<Source>
     */
    private static function sources(string $root, string $composerFile, array $composer, array &$errors): array
    {
        $autoload = is_array($composer['autoload'] ?? null) ? $composer['autoload'] : [];
        $psr4 = $autoload['psr-4'] ?? null;
        if (!is_array($psr4) || $psr4 === []) {
            $errors[] = sprintf('%s defines no autoload.psr-4 entries.', $composerFile);

            return [];
        }

        $sources = [];
        foreach ($psr4 as $prefix => $directories) {
            foreach ((array) $directories as $directory) {
                if (!is_string($directory)) {
                    $errors[] = sprintf('PSR-4 prefix "%s" in %s has a directory that is not a string.', $prefix, $composerFile);
                    continue;
                }
                $path = realpath($root . '/' . rtrim($directory, '/'));
                if ($path === false || !is_dir($path)) {
                    $errors[] = sprintf('PSR-4 directory "%s" of prefix "%s" in %s does not exist.', $directory, $prefix, $composerFile);
                    continue;
                }
                $sources[] = new Source(rtrim((string) $prefix, '\\') . '\\', $path);
            }
        }

        return $sources;
    }

    /**
     * @param array<mixed>          $composer
     * @param non-empty-string|null $parent   The parent theme of a child theme: its public callables need no declaration.
     * @param list<string>          $errors
     * @return list<non-empty-string>
     */
    private static function templateProviders(string $composerFile, array $composer, string $slug, ?string $parent, array &$errors): array
    {
        $extra = is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
        $settings = $extra['wp-app-framework'] ?? [];
        if (!is_array($settings)) {
            $errors[] = sprintf('extra.wp-app-framework in %s must be an object.', $composerFile);

            return [];
        }

        $plugins = $settings['template-plugins'] ?? [];
        if (!is_array($plugins) || !array_is_list($plugins)) {
            $errors[] = sprintf('extra.wp-app-framework.template-plugins in %s must be a list of plugin slugs.', $composerFile);

            return [];
        }

        $valid = [];
        $prefixes = [];
        if ($parent !== null) {
            $valid[] = $parent;
            $prefixes[$parent] = self::templatePrefix($parent);
        }
        foreach ($plugins as $plugin) {
            if (!is_string($plugin) || $plugin === '' || preg_match(self::SLUG_PATTERN, $plugin) !== 1) {
                $errors[] = sprintf('extra.wp-app-framework.template-plugins in %s contains %s, which is not a plugin slug.', $composerFile, json_encode($plugin));
                continue;
            }
            if ($plugin === $slug) {
                $errors[] = sprintf('extra.wp-app-framework.template-plugins in %s lists the project itself (%s).', $composerFile, $plugin);
                continue;
            }
            if ($plugin === $parent) {
                $errors[] = sprintf('extra.wp-app-framework.template-plugins in %s lists the parent theme %s; a child theme uses its public template callables without declaring them.', $composerFile, $plugin);
                continue;
            }
            $prefix = self::templatePrefix($plugin);
            foreach ($prefixes as $otherPlugin => $otherPrefix) {
                if (str_starts_with($prefix, $otherPrefix) || str_starts_with($otherPrefix, $prefix)) {
                    $errors[] = sprintf(
                        'extra.wp-app-framework.template-plugins in %s: the template prefixes %s of %s and %s of %s%s overlap; a template name could not be assigned to one plugin or theme.',
                        $composerFile,
                        $prefix,
                        $plugin,
                        $otherPrefix,
                        $otherPlugin === $parent ? 'the parent theme ' : '',
                        $otherPlugin,
                    );
                    continue 2;
                }
            }
            $prefixes[$plugin] = $prefix;
            $valid[] = $plugin;
        }

        return $valid;
    }

    /**
     * Namespaces of the installed dependencies (without development packages)
     * and the Strauss prefix: a release prefixes them, so their names differ in
     * every consumer.
     *
     * @param array<mixed> $composer
     * @return list<non-empty-string>
     */
    private static function foreignNamespaces(string $root, array $composer): array
    {
        $namespaces = [];

        $extra = is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
        $strauss = is_array($extra['strauss'] ?? null) ? $extra['strauss'] : [];
        if (is_string($strauss['namespace_prefix'] ?? null) && $strauss['namespace_prefix'] !== '') {
            $namespaces[rtrim($strauss['namespace_prefix'], '\\') . '\\'] = true;
        }

        $installedFile = $root . '/vendor/composer/installed.json';
        $installed = is_file($installedFile) ? json_decode((string) file_get_contents($installedFile), true) : null;
        if (is_array($installed)) {
            $development = is_array($installed['dev-package-names'] ?? null) ? $installed['dev-package-names'] : [];
            $packages = is_array($installed['packages'] ?? null) ? $installed['packages'] : [];
            foreach ($packages as $package) {
                if (!is_array($package) || in_array($package['name'] ?? null, $development, true)) {
                    continue;
                }
                $autoload = is_array($package['autoload'] ?? null) ? $package['autoload'] : [];
                foreach (['psr-4', 'psr-0'] as $type) {
                    foreach (array_keys(is_array($autoload[$type] ?? null) ? $autoload[$type] : []) as $namespace) {
                        $namespace = rtrim((string) $namespace, '\\');
                        if ($namespace !== '') {
                            $namespaces[$namespace . '\\'] = true;
                        }
                    }
                }
            }
        }

        $names = array_keys($namespaces);
        sort($names, SORT_STRING);

        return $names;
    }
}
