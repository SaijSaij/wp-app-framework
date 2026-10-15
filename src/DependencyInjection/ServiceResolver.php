<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\DependencyInjection;

use Attribute;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Saij\WpAppFramework\Api\PluginApi;
use Saij\WpAppFramework\Api\ThemeApi;
use Saij\WpAppFramework\ChildTheme;
use Saij\WpAppFramework\Plugin;
use Saij\WpAppFramework\Project\ComposerProject;
use Saij\WpAppFramework\Theme;

/**
 * Resolves constructor dependencies of reachable services.
 *
 * Only services reached from entry points are resolved, so classes that are
 * never injected (DTOs, attribute classes) never produce errors.
 *
 * @phpstan-import-type ServiceArgument from Container
 */
#[NoService]
final class ServiceResolver
{
    /** Descriptions of the consumer: injectable only where the framework provides them (predefined). */
    private const array CONSUMER_CLASSES = [
        Theme::class => 'available in themes only',
        ChildTheme::class => 'available in child themes only',
        Plugin::class => 'available in plugins only',
    ];

    /** @var array<class-string, array<string, ServiceArgument>> */
    private array $resolved = [];

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param array<class-string, ReflectionClass<object>> $candidates
     * @param array<class-string, string>                  $excluded   Scanned non-candidates and the reason, e.g. "an interface".
     * @param list<class-string>                           $predefined Candidates provided at runtime instead of built.
     * @param non-empty-string|null                        $parentTheme Slug of the parent theme of a child theme (`#[ThemeApi]`).
     * @param list<class-string>                           $settingsGroups Classes with `#[SettingsGroup]`, injected from their option.
     * @param list<string>                                 $unloaded   Excluded classes the build does not load.
     */
    public function __construct(
        private readonly array $candidates,
        private readonly array $excluded,
        private readonly array $predefined,
        private readonly ?string $parentTheme = null,
        private readonly array $settingsGroups = [],
        private readonly array $unloaded = [],
    ) {}

    /**
     * Classifies scanned classes into service candidates and excluded classes.
     *
     * @param array<class-string, ReflectionClass<object>> $classes
     * @param list<class-string>                           $predefined Candidates provided at runtime instead of built.
     * @param non-empty-string|null                        $parentTheme Slug of the parent theme of a child theme.
     * @param list<class-string>                           $settingsGroups Classes with `#[SettingsGroup]`: no services, injected
     *                                                                     from their option.
     * @param array<class-string, string>                  $nonServices    Classes that are data, not services, and what they
     *                                                                     are (post, meta, taxonomy and row classes).
     * @param array<string, string>                        $unloaded       Scanned classes the build does not load (they build
     *                                                                     on a class outside the project) and why.
     */
    public static function forClasses(array $classes, array $predefined, ?string $parentTheme = null, array $settingsGroups = [], array $nonServices = [], array $unloaded = []): self
    {
        $candidates = [];
        /** @var array<class-string, string> $excluded Not loaded, but the names of scanned classes. */
        $excluded = $unloaded;
        $unloadedNames = array_keys($unloaded);
        foreach ($classes as $name => $class) {
            $reason = match (true) {
                in_array($name, $predefined, true) => null,
                in_array($name, $settingsGroups, true) => 'a settings group',
                isset($nonServices[$name]) => $nonServices[$name],
                default => self::CONSUMER_CLASSES[$name] ?? self::exclusionReason($class),
            };
            if ($reason === null) {
                $candidates[$name] = $class;
            } else {
                $excluded[$name] = $reason;
            }
        }

        return new self($candidates, $excluded, $predefined, $parentTheme, $settingsGroups, $unloadedNames);
    }

    /**
     * Why a scanned class is not a service candidate, or null if it is one.
     *
     * @param ReflectionClass<object> $class
     */
    public static function exclusionReason(ReflectionClass $class): ?string
    {
        return match (true) {
            $class->isInterface() => 'an interface',
            $class->isTrait() => 'a trait',
            $class->isEnum() => 'an enum',
            $class->isAbstract() => 'an abstract class',
            $class->getAttributes(Attribute::class) !== [] => 'an attribute class',
            $class->getAttributes(NoService::class) !== [] => 'marked #[NoService]',
            default => null,
        };
    }

    /**
     * @param class-string $class
     */
    public function isCandidate(string $class): bool
    {
        return isset($this->candidates[$class]);
    }

    /**
     * Why a scanned class is excluded, or null if it is a candidate or was not scanned.
     *
     * @param class-string $class
     */
    public function exclusion(string $class): ?string
    {
        return $this->excluded[$class] ?? null;
    }

    /**
     * @return list<class-string> Sorted names of all service candidates.
     */
    public function candidates(): array
    {
        $names = array_keys($this->candidates);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * Returns the candidate class a parameter is injected with, or null if it is not injected.
     *
     * @return class-string|null
     */
    public function injectedType(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && isset($this->candidates[$type->getName()])) {
            /** @var class-string $class A key of the candidates. */
            $class = $type->getName();

            return $class;
        }

        return null;
    }

    /**
     * The settings group class a parameter declares as type, or null.
     *
     * @return class-string|null
     */
    public function settingsGroupType(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && in_array($type->getName(), $this->settingsGroups, true)) {
            /** @var class-string $class One of the settings groups. */
            $class = $type->getName();

            return $class;
        }

        return null;
    }

    /**
     * Whether a union or intersection type contains a service candidate (ambiguous, not supported).
     */
    public function hasAmbiguousType(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionUnionType && !$type instanceof ReflectionIntersectionType) {
            return false;
        }

        foreach ($type->getTypes() as $part) {
            if ($part instanceof ReflectionNamedType && isset($this->candidates[$part->getName()])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string       $class
     * @param list<class-string> $path  Services currently being resolved, for cycle detection.
     */
    public function resolve(string $class, array $path = []): void
    {
        if (isset($this->resolved[$class]) || in_array($class, $this->predefined, true)) {
            return;
        }

        if (in_array($class, $path, true)) {
            $cycle = array_slice($path, (int) array_search($class, $path, true));
            $this->errors[] = sprintf('Circular dependency: %s -> %s', implode(' -> ', $cycle), $class);

            return;
        }

        $path[] = $class;
        $arguments = [];
        $constructor = $this->candidates[$class]->getConstructor();

        if ($constructor !== null && !$constructor->isPublic()) {
            $this->errors[] = sprintf('%s::__construct() must be public to be built by the container.', $class);
        }

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $location = sprintf('parameter $%s of %s::__construct()', $parameter->getName(), $class);

            if ($parameter->isVariadic()) {
                $this->errors[] = sprintf('Cannot autowire %s: variadic parameters are not supported.', $location);
                continue;
            }

            if ($this->hasAmbiguousType($parameter)) {
                $this->errors[] = sprintf('Cannot autowire %s: union and intersection types are not supported.', $location);
                continue;
            }

            $pluginApi = $parameter->getAttributes(PluginApi::class);
            $themeApi = $parameter->getAttributes(ThemeApi::class);
            if ($pluginApi !== [] && $themeApi !== []) {
                $this->errors[] = sprintf('%s has both #[PluginApi] and #[ThemeApi]; use one.', ucfirst($location));
                continue;
            }
            if ($pluginApi !== [] || $themeApi !== []) {
                $argument = $pluginApi !== []
                    ? $this->apiArgument($parameter, 'plugin', $pluginApi[0]->newInstance()->plugin, $location)
                    : $this->themeApiArgument($parameter, $location);
                if ($argument !== null) {
                    $arguments[$parameter->getName()] = $argument;
                }
                continue;
            }

            $settingsGroup = $this->settingsGroupType($parameter);
            if ($settingsGroup !== null) {
                $arguments[$parameter->getName()] = ['setting' => $settingsGroup];
                continue;
            }

            $service = $this->injectedType($parameter);

            if ($service !== null) {
                $this->resolve($service, $path);
                $arguments[$parameter->getName()] = $service;
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                continue;
            }

            $this->errors[] = sprintf('Cannot autowire %s: %s', $location, $this->explain($parameter));
        }

        $this->resolved[$class] = $arguments;
    }

    /**
     * Validates a `#[ThemeApi]` parameter: only child themes have a parent theme.
     *
     * @return array{provider: non-empty-string, kind: 'theme', class: class-string}|null
     */
    private function themeApiArgument(ReflectionParameter $parameter, string $location): ?array
    {
        if ($this->parentTheme === null) {
            $this->errors[] = sprintf('#[ThemeApi] on %s: only child themes receive the public API of their parent theme; this project is no child theme (no "Template:" header in style.css).', $location);

            return null;
        }

        return $this->apiArgument($parameter, 'theme', $this->parentTheme, $location);
    }

    /**
     * Validates a `#[PluginApi]` or `#[ThemeApi]` parameter; the class is not
     * loaded, because the provider may be missing.
     *
     * @template K of 'plugin'|'theme'
     * @param K $kind
     * @return array{provider: non-empty-string, kind: K, class: class-string}|null
     */
    private function apiArgument(ReflectionParameter $parameter, string $kind, string $provider, string $location): ?array
    {
        $attribute = $kind === 'plugin' ? '#[PluginApi]' : '#[ThemeApi]';
        if ($provider === '' || preg_match(ComposerProject::SLUG_PATTERN, $provider) !== 1) {
            $this->errors[] = sprintf('%s on %s names "%s", which is not a %s slug (lowercase letters, digits, "-", "_").', $attribute, $location, $provider, $kind);

            return null;
        }

        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            $this->errors[] = sprintf('%s on %s needs the class of the public API as type.', $attribute, $location);

            return null;
        }
        if (!$type->allowsNull() || !$parameter->isDefaultValueAvailable() || $parameter->getDefaultValue() !== null) {
            $this->errors[] = sprintf('%s on %s must be nullable with the default null (?%s $%s = null): it is null if the %s is not active.', $attribute, $location, $type->getName(), $parameter->getName(), $kind);

            return null;
        }

        /** @var class-string $class Not loaded: the provider may be missing. */
        $class = $type->getName();
        if (isset($this->candidates[$class]) || isset($this->excluded[$class])) {
            $this->errors[] = sprintf('%s on %s: %s belongs to this project or the framework, not to %s %s.', $attribute, $location, $class, $kind, $provider);

            return null;
        }

        return ['provider' => $provider, 'kind' => $kind, 'class' => $class];
    }

    /**
     * Why a parameter without default value is not injected.
     */
    public function explain(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();

        if ($type === null) {
            return 'it has no type and no default value.';
        }

        if (!$type instanceof ReflectionNamedType) {
            return sprintf('type %s is not a service and the parameter has no default value.', $type);
        }

        if ($type->isBuiltin()) {
            return sprintf('type %s is not a service and the parameter has no default value.', $type->getName());
        }

        $name = $type->getName();

        if (isset($this->excluded[$name])) {
            return sprintf(
                '%s is %s and cannot be injected%s.',
                $name,
                $this->excluded[$name],
                match (true) {
                    in_array($name, $this->unloaded, true) => sprintf('; create it yourself where you need it (new %s(…) in a hook, for example)', substr($name, (int) strrpos($name, '\\') + 1)),
                    default => '',
                } . match ($this->excluded[$name]) {
                    'an interface' => ' (aliases are not supported)',
                    'a post class' => sprintf('; load posts with %s::get($id)', substr($name, (int) strrpos($name, '\\') + 1)),
                    'a meta class' => '; meta objects are reached through their post: $post->meta(…)',
                    'a row class' => sprintf('; load rows with %s::get($id)', substr($name, (int) strrpos($name, '\\') + 1)),
                    default => '',
                },
            );
        }

        return sprintf(
            '%s is outside the scanned PSR-4 directories of the project and the framework and cannot be injected.',
            $name,
        );
    }

    /**
     * @return array<class-string, array<string, ServiceArgument>>
     */
    public function resolved(): array
    {
        $resolved = $this->resolved;
        ksort($resolved, SORT_STRING);

        return $resolved;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
