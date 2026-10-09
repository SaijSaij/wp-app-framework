<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\DependencyInjection;

use Attribute;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * Resolves constructor dependencies of reachable services.
 *
 * Only services reached from entry points are resolved, so classes that are
 * never injected (DTOs, attribute classes) never produce errors.
 */
#[NoService]
final class ServiceResolver
{
    /** @var array<class-string, array<string, class-string>> */
    private array $resolved = [];

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param array<class-string, ReflectionClass<object>> $candidates
     * @param array<class-string, string>                  $excluded   Scanned non-candidates and the reason, e.g. "an interface".
     * @param list<class-string>                           $predefined Candidates provided at runtime instead of built.
     */
    public function __construct(
        private readonly array $candidates,
        private readonly array $excluded,
        private readonly array $predefined,
    ) {
    }

    /**
     * Classifies scanned classes into service candidates and excluded classes.
     *
     * @param array<class-string, ReflectionClass<object>> $classes
     * @param list<class-string>                           $predefined Candidates provided at runtime instead of built.
     */
    public static function forClasses(array $classes, array $predefined): self
    {
        $candidates = [];
        $excluded = [];
        foreach ($classes as $name => $class) {
            $reason = in_array($name, $predefined, true) ? null : self::exclusionReason($class);
            if ($reason === null) {
                $candidates[$name] = $class;
            } else {
                $excluded[$name] = $reason;
            }
        }

        return new self($candidates, $excluded, $predefined);
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
            return $type->getName();
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
                $this->excluded[$name] === 'an interface' ? ' (aliases are not supported)' : '',
            );
        }

        return sprintf(
            '%s is outside the scanned PSR-4 directories of the theme and the framework and cannot be injected.',
            $name,
        );
    }

    /**
     * @return array<class-string, array<string, class-string>>
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
