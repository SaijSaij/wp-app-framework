<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Hook;

use ReflectionClass;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Manifest\EntryPointInspector;
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

    private readonly EntryPointInspector $inspector;

    public function __construct(private readonly ServiceResolver $resolver)
    {
        $this->inspector = new EntryPointInspector($resolver);
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

                $error = $this->inspector->serviceError($name, $location, '#[Hook]')
                    ?? $this->inspector->publicInstanceMethodError($method, $location, '#[Hook]')
                    ?? $this->inspector->returnTypeError($method, $location, '#[Hook]', 'void for an action, the filtered type for a filter');
                if ($error !== null) {
                    $this->errors[] = $error;
                    continue;
                }
                $filter = !EntryPointInspector::isVoid($method);

                $arguments = [];
                $services = [];
                $index = 0;
                $valid = true;
                foreach ($method->getParameters() as $parameter) {
                    $parameterLocation = sprintf('parameter $%s of %s', $parameter->getName(), $location);

                    $error = $this->inspector->parameterError($parameter, $parameterLocation, 'hooks');
                    if ($error !== null) {
                        $this->errors[] = $error;
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
