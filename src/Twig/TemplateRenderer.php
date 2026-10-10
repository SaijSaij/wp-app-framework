<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Twig;

use Saij\WpAppFramework\Api\PluginBridge;
use Saij\WpAppFramework\DependencyInjection\Container;
use Stringable;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\LoaderInterface;
use Twig\Markup;
use Twig\Runtime\EscaperRuntime;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Renders the Twig templates of a framework instance (theme or plugin).
 *
 * Template names are paths relative to the `src/` directory of each level,
 * including the extension (e.g. `Component/Button.twig`). Names without a
 * namespace are looked up in the search paths of the instance, then in the
 * framework, so a higher level overrides a template by providing the same path:
 * - theme: child theme `src/` → theme `src/` → framework,
 * - plugin: child theme `overrides/{slug}/` → theme `overrides/{slug}/` → plugin `src/` → framework.
 * `@theme/…` (themes), `@plugin/…` (plugins) and `@framework/…` address exactly
 * one level and cannot be overridden.
 *
 * Names starting with the template prefix of a plugin declared in
 * `extra.wp-app-framework.template-plugins` call the public template functions
 * and filters of that plugin.
 *
 * Provided by the framework at runtime; services receive it through their
 * constructor. The Twig environment is created on the first render.
 *
 * @phpstan-type TemplateCallableEntry array{kind: value-of<TemplateCallableKind>, name: string, class: class-string, method: string, html: bool, public: bool}
 */
final class TemplateRenderer
{
    /** Directory of the templates inside each level, relative to its root. */
    public const string TEMPLATE_DIRECTORY = 'src';

    /** Directory of a theme with overrides of plugin templates, one subdirectory per plugin slug. */
    public const string OVERRIDE_DIRECTORY = 'overrides';

    private ?Environment $environment = null;

    /** @var array<string, TwigFunction|TwigFilter> Public callables of plugins by kind and name. */
    private array $pluginCallables = [];

    /**
     * @param non-empty-string                          $slug               Slug of the instance; part of the template cache keys.
     * @param string                                    $rootPath           Root of the project; cache keys are relative to it.
     * @param list<string>                              $overridePaths      Existing directories searched before the project's templates
     *                                                                      (child theme `src/`; for plugins the `overrides/{slug}/`
     *                                                                      directories of child theme and theme).
     * @param string                                    $projectTemplates   The project's `src/`.
     * @param string                                    $exactNamespace     Namespace of the project's own templates (`theme`, `plugin`).
     * @param string                                    $frameworkTemplates Template directory of the framework (its `src/`).
     * @param string                                    $cacheDirectory     Directory of the compiled templates. In release mode it must be
     *                                                                      specific to the release (see TemplateVersion): templates are
     *                                                                      never recompiled there.
     * @param bool                                      $development        Development mode: debug, auto reload and dump().
     * @param list<TemplateCallableEntry>               $callables          Template functions and filters from the manifest.
     * @param array<non-empty-string, non-empty-string> $templatePlugins    Template prefix => slug of the plugins whose public
     *                                                                      template callables the templates use.
     */
    public function __construct(
        private readonly string $slug,
        private readonly string $rootPath,
        private readonly array $overridePaths,
        private readonly string $projectTemplates,
        private readonly string $exactNamespace,
        private readonly string $frameworkTemplates,
        private readonly string $cacheDirectory,
        private readonly bool $development,
        private readonly array $callables,
        private readonly array $templatePlugins,
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

    /**
     * Compiles a template into the cache directory without rendering it.
     *
     * @internal Used by the release build to check the compiled code.
     */
    public function compile(string $name): void
    {
        $this->getEnvironment()->load($name);
    }

    private function getEnvironment(): Environment
    {
        if ($this->environment !== null) {
            return $this->environment;
        }

        $loader = new InstanceLoader($this->slug, $this->rootPath);
        foreach ($this->overridePaths as $path) {
            $loader->addPath($path);
        }
        $loader->addPath($this->projectTemplates);
        $loader->addPath($this->frameworkTemplates);
        $loader->addPath($this->projectTemplates, $this->exactNamespace);
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

        if ($this->templatePlugins !== []) {
            // Twig resolves names while compiling, also when the plugin is missing; the plugin is asked on each call.
            $environment->registerUndefinedFunctionCallback(fn(string $name): TwigFunction|false => $this->pluginFunction($name));
            $environment->registerUndefinedFilterCallback(fn(string $name): TwigFilter|false => $this->pluginFilter($name));
        }

        return $this->environment = $environment;
    }

    private function pluginFunction(string $name): TwigFunction|false
    {
        $plugin = $this->pluginOf($name);
        if ($plugin === null) {
            return false;
        }

        $function = $this->pluginCallables['function:' . $name] ??= new TwigFunction(
            $name,
            static function (mixed ...$arguments) use ($plugin, $name): mixed {
                $entry = PluginBridge::templateCallable($plugin, TemplateCallableKind::Function, $name);
                $result = ($entry['callable'])(...self::plainArguments($arguments));

                return $entry['html'] && is_string($result) ? new Markup($result, 'UTF-8') : $result;
            },
            ['is_variadic' => true],
        );

        return $function instanceof TwigFunction ? $function : false;
    }

    private function pluginFilter(string $name): TwigFilter|false
    {
        $plugin = $this->pluginOf($name);
        if ($plugin === null) {
            return false;
        }

        $filter = $this->pluginCallables['filter:' . $name] ??= new TwigFilter(
            $name,
            static function (Environment $environment, mixed $value, mixed ...$arguments) use ($plugin, $name): mixed {
                $entry = PluginBridge::templateCallable($plugin, TemplateCallableKind::Filter, $name);
                // The HTML flag is known only now, so the value is escaped here instead of by Twig's pre_escape.
                if ($entry['html'] && !$value instanceof Markup && (is_string($value) || $value instanceof Stringable)) {
                    $value = $environment->getRuntime(EscaperRuntime::class)->escape($value, 'html', 'UTF-8');
                }
                $result = ($entry['callable'])(...self::plainArguments([$value, ...$arguments]));

                return $entry['html'] && is_string($result) ? new Markup($result, 'UTF-8') : $result;
            },
            ['is_variadic' => true, 'needs_environment' => true],
        );

        return $filter instanceof TwigFilter ? $filter : false;
    }

    /**
     * Safe strings of this instance (`Markup`) as plain strings: the plugin may
     * run another, differently prefixed Twig, so no Twig object crosses over.
     *
     * @param array<mixed> $arguments
     * @return array<mixed>
     */
    private static function plainArguments(array $arguments): array
    {
        return array_map(static fn(mixed $argument): mixed => $argument instanceof Markup ? (string) $argument : $argument, $arguments);
    }

    /**
     * The slug of the plugin whose template prefix the name starts with.
     */
    private function pluginOf(string $name): ?string
    {
        foreach ($this->templatePlugins as $prefix => $plugin) {
            if (str_starts_with($name, $prefix)) {
                return $plugin;
            }
        }

        return null;
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
