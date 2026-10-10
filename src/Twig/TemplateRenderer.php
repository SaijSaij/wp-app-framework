<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Twig;

use LogicException;
use Saij\WpAppFramework\DependencyInjection\Container;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Renders Twig templates of the child theme, the theme and the framework.
 *
 * Template names are paths relative to the `src/` directory of each level,
 * including the extension (e.g. `Component/Button.twig`). Names without a
 * namespace are looked up in the child theme, then the theme, then the
 * framework, so a higher level overrides a template by providing the same path.
 * `@theme/…` and `@framework/…` address exactly one level and cannot be
 * overridden.
 *
 * Provided by the framework at runtime; services receive it through their
 * constructor. The Twig environment is created on the first render.
 *
 * @phpstan-type TemplateCallableEntry array{kind: value-of<TemplateCallableKind>, name: string, class: class-string, method: string, html: bool}
 */
final class TemplateRenderer
{
    /** Directory of the templates inside each level, relative to its root. */
    public const string TEMPLATE_DIRECTORY = 'src';

    private ?Environment $environment = null;

    /**
     * @param string                      $templateDirectory  Root of the theme (get_template_directory()).
     * @param string                      $stylesheetDirectory Root of the active theme (get_stylesheet_directory()); differs from
     *                                                         $templateDirectory if a child theme is active.
     * @param string                      $frameworkTemplates Template directory of the framework (its `src/`).
     * @param string                      $cacheDirectory     Directory of the compiled templates.
     * @param bool                        $development        Development mode: debug, auto reload and dump().
     * @param list<TemplateCallableEntry> $callables          Template functions and filters from the manifest.
     */
    public function __construct(
        private readonly string $templateDirectory,
        private readonly string $stylesheetDirectory,
        private readonly string $frameworkTemplates,
        private readonly string $cacheDirectory,
        private readonly bool $development,
        private readonly array $callables,
        private readonly Container $container,
    ) {}

    /**
     * Renders a template.
     *
     * @param string               $name    E.g. `Component/Button.twig` or `@framework/Component/Button.twig`.
     * @param array<string, mixed> $context Template variables.
     */
    public function render(string $name, array $context = []): string
    {
        return $this->getEnvironment()->render($name, $context);
    }

    /**
     * Names of the functions and filters Twig provides itself in development
     * mode (a superset of release mode). Template callables must not reuse them:
     * Twig would silently replace the built-in one.
     *
     * @internal Used by the build.
     *
     * @return array{function: list<string>, filter: list<string>}
     */
    public static function getBuiltInNames(): array
    {
        $names = ['function' => [], 'filter' => []];
        foreach (self::createEnvironment(new ArrayLoader(), true, false)->getExtensions() as $extension) {
            foreach ($extension->getFunctions() as $function) {
                $names['function'][] = $function->getName();
            }
            foreach ($extension->getFilters() as $filter) {
                $names['filter'][] = $filter->getName();
            }
        }

        return $names;
    }

    private function getEnvironment(): Environment
    {
        if ($this->environment !== null) {
            return $this->environment;
        }

        if (!$this->development) {
            // Without auto reload, Twig never recompiles a cached template; after a theme update the
            // cache directory must change. The release build will provide a template version for that.
            throw new LogicException('Twig templates cannot be rendered in release mode yet: the release build does not provide a template cache version.');
        }

        $themeTemplates = $this->templateDirectory . '/' . self::TEMPLATE_DIRECTORY;
        $loader = new FilesystemLoader([], $this->templateDirectory);

        // A child theme is optional and needs no templates of its own.
        $childTemplates = $this->stylesheetDirectory . '/' . self::TEMPLATE_DIRECTORY;
        if ($this->stylesheetDirectory !== $this->templateDirectory && is_dir($childTemplates)) {
            $loader->addPath($childTemplates);
        }
        $loader->addPath($themeTemplates);
        $loader->addPath($this->frameworkTemplates);
        $loader->addPath($themeTemplates, 'theme');
        $loader->addPath($this->frameworkTemplates, 'framework');

        $environment = self::createEnvironment($loader, $this->development, $this->cacheDirectory);

        $classes = [];
        foreach ($this->callables as $callable) {
            $safe = $callable['html'] ? ['html'] : null;
            $target = [$callable['class'], $callable['method']];
            match (TemplateCallableKind::from($callable['kind'])) {
                TemplateCallableKind::Function => $environment->addFunction(new TwigFunction($callable['name'], $target, ['is_safe' => $safe])),
                // An HTML filter receives its value escaped unless the value is already safe (like Twig's nl2br).
                TemplateCallableKind::Filter => $environment->addFilter(new TwigFilter($callable['name'], $target, [
                    'is_safe' => $safe,
                    'pre_escape' => $callable['html'] ? 'html' : null,
                ])),
            };
            $classes[$callable['class']] = true;
        }
        $environment->addRuntimeLoader(new RuntimeLoader($this->container, $classes));

        return $this->environment = $environment;
    }

    private static function createEnvironment(LoaderInterface $loader, bool $development, string|false $cacheDirectory): Environment
    {
        $environment = new Environment($loader, [
            'autoescape' => 'html',
            'strict_variables' => true,
            'charset' => 'UTF-8',
            'debug' => $development,
            'auto_reload' => $development,
            'cache' => $cacheDirectory === false
                ? false
                : new FilesystemCache($cacheDirectory, FilesystemCache::FORCE_BYTECODE_INVALIDATION),
        ]);

        if ($development) {
            $environment->addExtension(new DebugExtension());
        }

        return $environment;
    }
}
