<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Saij\WpAppFramework\Api\PluginApi;
use Saij\WpAppFramework\Api\ThemeApi;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;

/**
 * Checks shared by the collectors of entry point attributes such as `#[Hook]`
 * and `#[RestRoute]`. Each check returns an error message or null.
 */
#[NoService]
final readonly class EntryPointInspector
{
    public function __construct(private ServiceResolver $resolver) {}

    /**
     * @param class-string $class
     * @param string       $attribute E.g. "#[Hook]".
     */
    public function serviceError(string $class, string $location, string $attribute): ?string
    {
        if ($this->resolver->isCandidate($class)) {
            return null;
        }

        return sprintf('%s has %s, but %s is %s and therefore not a service.', $location, $attribute, $class, $this->resolver->exclusion($class));
    }

    public function publicInstanceMethodError(ReflectionMethod $method, string $location, string $attribute): ?string
    {
        if ($method->isPublic() && !$method->isStatic()) {
            return null;
        }

        return sprintf('%s has %s and must be public and non-static.', $location, $attribute);
    }

    /**
     * @param string $expected Explains the expected return type, e.g. "void for an action, the filtered type for a filter".
     */
    public function returnTypeError(ReflectionMethod $method, string $location, string $attribute, string $expected): ?string
    {
        $returnType = $method->getReturnType();
        if ($returnType === null) {
            return sprintf('%s has %s and needs a declared return type: %s.', $location, $attribute, $expected);
        }
        if ($returnType instanceof ReflectionNamedType && $returnType->getName() === 'never') {
            return sprintf('%s has %s and must not return never.', $location, $attribute);
        }

        return null;
    }

    /**
     * @param string $context Where the parameter is used, e.g. "hooks".
     */
    public function parameterError(ReflectionParameter $parameter, string $parameterLocation, string $context): ?string
    {
        if ($parameter->isVariadic() || $parameter->isPassedByReference()) {
            return sprintf('%s: variadic and by-reference parameters are not supported in %s.', $parameterLocation, $context);
        }

        if ($this->resolver->hasAmbiguousType($parameter)) {
            return sprintf('%s: union and intersection types containing a service are not supported.', $parameterLocation);
        }

        foreach ([PluginApi::class => '#[PluginApi]', ThemeApi::class => '#[ThemeApi]'] as $class => $attribute) {
            if ($parameter->getAttributes($class) !== []) {
                return sprintf('%s: %s is only supported on constructor parameters of services, not in %s.', $parameterLocation, $attribute, $context);
            }
        }

        return null;
    }

    public static function isVoid(ReflectionMethod $method): bool
    {
        $returnType = $method->getReturnType();

        return $returnType instanceof ReflectionNamedType && $returnType->getName() === 'void';
    }
}
