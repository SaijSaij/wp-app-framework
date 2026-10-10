<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest;

use JsonException;
use ReflectionClass;
use Saij\WpAppFramework\Block\BlockCollector;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Hook\HookCollector;
use Saij\WpAppFramework\Manifest\Discovery\ClassDiscovery;
use Saij\WpAppFramework\Manifest\Discovery\Source;
use Saij\WpAppFramework\Rest\RestRouteCollector;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateCallableCollector;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Value\ValueSchemaBuilder;
use Throwable;

/**
 * Loads the classes of the PSR-4 sources, lets the entry point collectors find
 * their attributes and resolves the services reachable from them. Runs only in
 * the build and in development mode.
 */
#[NoService]
final class ManifestBuilder
{
    /**
     * @param list<Source>       $sources
     * @param list<string>       $trackedFiles Absolute paths of files whose content affects the build.
     * @param list<class-string> $predefined   Candidates provided at runtime instead of built.
     * @param string|null        $scriptDirectory Directory the consumer's build compiles scripts from (its `src/`).
     */
    public function __construct(
        private readonly array $sources,
        private readonly array $trackedFiles = [],
        private readonly array $predefined = [],
        private readonly ?string $scriptDirectory = null,
    ) {}

    /**
     * Builder for a theme: its PSR-4 directories from composer.json plus the framework itself.
     */
    public static function forTheme(string $themeDirectory): self
    {
        $root = realpath($themeDirectory);
        if ($root === false) {
            throw new BuildException([sprintf('Theme directory %s does not exist.', $themeDirectory)]);
        }

        $composerFile = $root . '/composer.json';
        if (!is_file($composerFile)) {
            throw new BuildException([sprintf(
                '%s not found; the build reads the PSR-4 directories of the theme from it.',
                $composerFile,
            )]);
        }

        try {
            $composer = json_decode((string) file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new BuildException([sprintf('%s is not valid JSON: %s', $composerFile, $exception->getMessage())]);
        }

        $autoload = is_array($composer) && is_array($composer['autoload'] ?? null) ? $composer['autoload'] : [];
        $psr4 = $autoload['psr-4'] ?? null;
        if (!is_array($psr4) || $psr4 === []) {
            throw new BuildException([sprintf('%s defines no autoload.psr-4 entries.', $composerFile)]);
        }

        $sources = [];
        $errors = [];
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

        if ($errors !== []) {
            throw new BuildException($errors);
        }

        $framework = self::frameworkSource();
        $sources[] = $framework;

        $trackedFiles = [$composerFile];
        foreach (ClassDiscovery::files($framework->getDirectory()) as $relative) {
            $trackedFiles[] = $framework->getDirectory() . '/' . $relative;
        }

        $scriptDirectory = realpath($root . '/' . TemplateRenderer::TEMPLATE_DIRECTORY);

        return new self(
            $sources,
            $trackedFiles,
            [Theme::class, TemplateRenderer::class, RenderContext::class],
            $scriptDirectory === false ? null : $scriptDirectory,
        );
    }

    /**
     * The framework's own source directory. Derived from a framework class, so it
     * also matches the prefixed namespace and location of a release build.
     */
    public static function frameworkSource(): Source
    {
        $prefix = substr(Theme::class, 0, (int) strrpos(Theme::class, '\\') + 1);
        $directory = dirname((string) new ReflectionClass(Theme::class)->getFileName());

        return new Source($prefix, $directory);
    }

    public function build(): BuildResult
    {
        $errors = [];
        $classes = $this->loadClasses($errors);

        $resolver = ServiceResolver::forClasses($classes, $this->predefined);
        $values = new ValueSchemaBuilder($classes);
        $hookCollector = new HookCollector($resolver);
        $hooks = $hookCollector->collect($classes);
        $routeCollector = new RestRouteCollector($resolver, $values);
        $routes = $routeCollector->collect($classes);
        $blockCollector = new BlockCollector($resolver, $values, self::frameworkSource()->getDirectory(), $this->scriptDirectory);
        $blocks = $blockCollector->collect($classes);
        $templateCallableCollector = new TemplateCallableCollector($resolver, TemplateRenderer::getBuiltInNames());
        $templateCallables = $templateCallableCollector->collect($classes);

        $errors = [
            ...$errors,
            ...$hookCollector->errors(),
            ...$routeCollector->errors(),
            ...$templateCallableCollector->errors(),
            ...$blockCollector->errors(),
            ...$values->errors(),
            ...$resolver->errors(),
        ];
        if ($errors !== []) {
            throw new BuildException($errors);
        }

        return new BuildResult(
            $this->sources,
            $this->trackedFiles,
            $classes,
            $resolver->candidates(),
            $resolver->resolved(),
            $hooks,
            $routes,
            $templateCallables,
            $blocks,
            $values->values(),
        );
    }

    /**
     * @param list<string> $errors
     * @return array<class-string, ReflectionClass<object>>
     */
    private function loadClasses(array &$errors): array
    {
        $classes = [];

        foreach ($this->sources as $source) {
            try {
                $found = ClassDiscovery::classes($source);
            } catch (BuildException $exception) {
                array_push($errors, ...$exception->getErrors());
                continue;
            }

            foreach ($found as $file => $name) {
                if (isset($classes[$name])) {
                    continue;
                }

                try {
                    $exists = class_exists($name)
                        || interface_exists($name, false)
                        || trait_exists($name, false)
                        || enum_exists($name, false);
                } catch (Throwable $exception) {
                    $errors[] = sprintf('Cannot load %s from %s: %s', $name, $file, $exception->getMessage());
                    continue;
                }

                if (!$exists) {
                    $errors[] = sprintf('%s does not declare %s as expected by PSR-4.', $file, $name);
                    continue;
                }

                $classes[$name] = new ReflectionClass($name);
            }
        }

        return $classes;
    }
}
