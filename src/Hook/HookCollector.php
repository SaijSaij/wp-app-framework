<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Hook;

use ReflectionClass;
use ReflectionNamedType;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Throwable;

/**
 * Collects and validates `#[Hook]` methods during the build and resolves the
 * services they need.
 */
#[NoService]
final class HookCollector
{
    /** @var list<string> */
    private array $errors = [];

    public function __construct(private readonly ServiceResolver $resolver)
    {
    }

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     * @return list<HookDefinition>
     */
    public function collect(array $classes): array
    {
        $hooks = [];

        foreach ($classes as $name => $class) {
            foreach ($class->getMethods() as $method) {
                $attributes = $method->getAttributes(Hook::class);
                if ($attributes === [] || $method->getDeclaringClass()->getName() !== $name) {
                    continue;
                }

                $location = sprintf('%s::%s()', $name, $method->getName());

                if (!$this->resolver->isCandidate($name)) {
                    $this->errors[] = sprintf('%s has #[Hook], but %s is %s and therefore not a service.', $location, $name, $this->resolver->exclusion($name));
                    continue;
                }

                if (!$method->isPublic() || $method->isStatic()) {
                    $this->errors[] = sprintf('%s has #[Hook] and must be public and non-static.', $location);
                    continue;
                }

                $returnType = $method->getReturnType();
                if ($returnType === null) {
                    $this->errors[] = sprintf('%s has #[Hook] and needs a declared return type: void for an action, the filtered type for a filter.', $location);
                    continue;
                }
                if ($returnType instanceof ReflectionNamedType && $returnType->getName() === 'never') {
                    $this->errors[] = sprintf('%s has #[Hook] and must not return never.', $location);
                    continue;
                }
                $filter = !($returnType instanceof ReflectionNamedType && $returnType->getName() === 'void');

                $arguments = [];
                $services = [];
                $index = 0;
                $valid = true;
                foreach ($method->getParameters() as $parameter) {
                    $parameterLocation = sprintf('parameter $%s of %s', $parameter->getName(), $location);

                    if ($parameter->isVariadic() || $parameter->isPassedByReference()) {
                        $this->errors[] = sprintf('%s: variadic and by-reference parameters are not supported in hooks.', $parameterLocation);
                        $valid = false;
                        continue;
                    }

                    if ($this->resolver->hasAmbiguousType($parameter)) {
                        $this->errors[] = sprintf('%s: union and intersection types containing a service are not supported.', $parameterLocation);
                        $valid = false;
                        continue;
                    }

                    $service = $this->resolver->injectedType($parameter);
                    if ($service !== null) {
                        $arguments[] = ['param' => $parameter->getName(), 'service' => $service];
                        $services[] = $service;
                    } else {
                        $arguments[] = ['param' => $parameter->getName(), 'index' => $index++];
                    }
                }

                if (!$valid) {
                    continue;
                }

                foreach ($attributes as $attribute) {
                    try {
                        $hook = $attribute->newInstance();
                    } catch (Throwable $exception) {
                        $this->errors[] = sprintf('Invalid #[Hook] on %s: %s', $location, $exception->getMessage());
                        continue;
                    }

                    if ($hook->name === '') {
                        $this->errors[] = sprintf('#[Hook] on %s has an empty hook name.', $location);
                        continue;
                    }

                    $hooks[] = new HookDefinition(
                        $hook->name,
                        $hook->priority,
                        $filter,
                        $name,
                        $method->getName(),
                        $arguments,
                        $filter ? max(1, $index) : $index,
                    );
                }

                $this->resolver->resolve($name);
                foreach ($services as $service) {
                    $this->resolver->resolve($service);
                }
            }
        }

        return $hooks;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
