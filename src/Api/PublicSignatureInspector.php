<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Api;

use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Checks the signatures a plugin or theme offers to other consumers (public APIs and
 * public template callables): they must not use classes of the framework or of
 * other dependencies, because a release prefixes them and their names differ
 * in every consumer. Own classes of the project used in a signature are checked
 * the same way (public methods and properties, without constructors).
 */
#[NoService]
final class PublicSignatureInspector
{
    /** @var array<class-string, true> Own classes already checked. */
    private array $checked = [];

    /**
     * @param array<class-string, ReflectionClass<object>> $classes            All scanned classes.
     * @param list<string>                                  $prefixed           Namespaces with a trailing backslash that a release prefixes.
     * @param string                                        $frameworkDirectory Source directory of the framework; its classes are not own classes.
     */
    public function __construct(
        private readonly array $classes,
        private readonly array $prefixed,
        private readonly string $frameworkDirectory,
    ) {}

    /**
     * @param ReflectionClass<object> $class
     * @return list<string>
     */
    public function classErrors(ReflectionClass $class, string $context): array
    {
        $this->checked = [];

        return $this->checkClass($class, $context);
    }

    /**
     * @return list<string>
     */
    public function methodErrors(ReflectionMethod $method, string $context): array
    {
        $this->checked = [];

        return $this->checkFunction($method, sprintf('%s::%s()', $method->getDeclaringClass()->getName(), $method->getName()), $context);
    }

    /**
     * @param ReflectionClass<object> $class
     * @return list<string>
     */
    private function checkClass(ReflectionClass $class, string $context): array
    {
        if (isset($this->checked[$class->getName()])) {
            return [];
        }
        $this->checked[$class->getName()] = true;

        $errors = [];
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor()) {
                continue;
            }
            array_push($errors, ...$this->checkFunction($method, sprintf('%s::%s()', $class->getName(), $method->getName()), $context));
        }
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $location = sprintf('property $%s of %s', $property->getName(), $class->getName());
            array_push($errors, ...$this->checkType($property->getType(), $location, $context));
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function checkFunction(ReflectionFunctionAbstract $function, string $location, string $context): array
    {
        $errors = [];
        foreach ($function->getParameters() as $parameter) {
            array_push($errors, ...$this->checkType($parameter->getType(), sprintf('parameter $%s of %s', $parameter->getName(), $location), $context));
        }
        array_push($errors, ...$this->checkType($function->getReturnType(), 'the return type of ' . $location, $context));

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function checkType(?ReflectionType $type, string $location, string $context): array
    {
        if ($type === null) {
            return [];
        }
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $errors = [];
            foreach ($type->getTypes() as $part) {
                array_push($errors, ...$this->checkType($part, $location, $context));
            }

            return $errors;
        }
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return [];
        }

        $name = $type->getName();

        // Own classes are part of the signature: check them too.
        $own = $this->classes[$name] ?? null;
        if ($own !== null && !str_starts_with((string) $own->getFileName(), $this->frameworkDirectory . '/')) {
            return $this->checkClass($own, $context);
        }

        foreach ($this->prefixed as $namespace) {
            if (str_starts_with($name, $namespace)) {
                return [sprintf(
                    '%s of %s uses %s; a release prefixes the namespace %s, so the class has a different name in every consumer. Use PHP, WordPress or own classes of the project.',
                    ucfirst($location),
                    $context,
                    $name,
                    $namespace,
                )];
            }
        }

        return [];
    }
}
