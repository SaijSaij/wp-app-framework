<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Twig;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Saij\WpAppFramework\Api\PublicSignatureInspector;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Manifest\EntryPointInspector;
use Throwable;

/**
 * Collects and validates `#[TemplateFunction]` and `#[TemplateFilter]` methods
 * during the build and resolves the services that provide them.
 */
#[NoService]
final class TemplateCallableCollector
{
    private const string NAME_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /** @var list<string> */
    private array $errors = [];

    /** @var array<value-of<TemplateCallableKind>, array<string, string>> Kind => name => location of the first declaration. */
    private array $declared = [];

    private readonly EntryPointInspector $inspector;

    /**
     * @param array{function: list<string>, filter: list<string>} $builtInNames    Names Twig provides itself.
     * @param non-empty-string|null                              $publicPrefix    Template prefix of a plugin or theme for its
     *                                                                            public callables; null for a child theme, which has none.
     * @param array<non-empty-string, non-empty-string>          $providerPrefixes Template prefix => slug of the plugins and the
     *                                                                            parent theme whose public callables the templates
     *                                                                            use; reserved.
     */
    public function __construct(
        private readonly ServiceResolver $resolver,
        private readonly array $builtInNames,
        private readonly ?string $publicPrefix = null,
        private readonly array $providerPrefixes = [],
        private readonly ?PublicSignatureInspector $signatures = null,
    ) {
        $this->inspector = new EntryPointInspector($resolver);
    }

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     * @return list<TemplateCallableDefinition>
     */
    public function collect(array $classes): array
    {
        $definitions = [];

        foreach ($classes as $name => $class) {
            foreach ($class->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $name) {
                    continue;
                }

                foreach (TemplateCallableKind::cases() as $kind) {
                    $definition = $this->collectMethod($name, $method, $kind);
                    if ($definition !== null) {
                        $definitions[] = $definition;
                    }
                }
            }
        }

        return $definitions;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param class-string $class
     */
    private function collectMethod(string $class, ReflectionMethod $method, TemplateCallableKind $kind): ?TemplateCallableDefinition
    {
        $attributeClass = match ($kind) {
            TemplateCallableKind::Function => TemplateFunction::class,
            TemplateCallableKind::Filter => TemplateFilter::class,
        };
        $attributes = $method->getAttributes($attributeClass);
        if ($attributes === []) {
            return null;
        }

        $attributeName = $kind->getAttributeName();
        $location = sprintf('%s::%s()', $class, $method->getName());

        try {
            $attribute = $attributes[0]->newInstance();
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid %s on %s: %s', $attributeName, $location, $exception->getMessage());

            return null;
        }

        $error = $this->inspector->serviceError($class, $location, $attributeName)
            ?? $this->inspector->publicInstanceMethodError($method, $location, $attributeName)
            ?? $this->nameError($attribute->name, $kind, $location)
            ?? $this->publicError($attribute->name, $attribute->public, $location, $attributeName)
            ?? $this->returnTypeError($method, $attribute->html, $location, $attributeName)
            ?? $this->parametersError($method, $kind, $location, $attributeName);
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }

        if ($attribute->public && $this->signatures !== null) {
            $signatureErrors = $this->signatures->methodErrors($method, sprintf('public template %s "%s"', $kind->value, $attribute->name));
            if ($signatureErrors !== []) {
                array_push($this->errors, ...$signatureErrors);

                return null;
            }
        }

        $this->declared[$kind->value][$attribute->name] = $location;
        $this->resolver->resolve($class);

        return new TemplateCallableDefinition($kind, $attribute->name, $class, $method->getName(), $attribute->html, $attribute->public);
    }

    private function nameError(string $name, TemplateCallableKind $kind, string $location): ?string
    {
        $attributeName = $kind->getAttributeName();

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            return sprintf('%s on %s has the invalid name "%s"; use letters, digits and underscores, not starting with a digit.', $attributeName, $location, $name);
        }

        if (in_array($name, $this->builtInNames[$kind->value], true)) {
            return sprintf('%s on %s uses the name "%s", which Twig already provides as a %s.', $attributeName, $location, $name, $kind->value);
        }

        $first = $this->declared[$kind->value][$name] ?? null;
        if ($first !== null) {
            return sprintf('%s on %s uses the name "%s", which %s already declares.', $attributeName, $location, $name, $first);
        }

        return null;
    }

    private function publicError(string $name, bool $public, string $location, string $attributeName): ?string
    {
        foreach ($this->providerPrefixes as $prefix => $provider) {
            if (str_starts_with($name, $prefix)) {
                return sprintf('%s on %s uses the name "%s"; names starting with "%s" are reserved for the public template callables of %s (template-plugins or parent theme).', $attributeName, $location, $name, $prefix, $provider);
            }
        }

        if (!$public) {
            return null;
        }
        if ($this->publicPrefix === null) {
            return sprintf('%s on %s has public: true, but only plugins and themes offer template callables to other consumers.', $attributeName, $location);
        }
        if (!str_starts_with($name, $this->publicPrefix) || $name === $this->publicPrefix) {
            return sprintf('%s on %s has public: true, so its name "%s" must start with the template prefix "%s" of the project.', $attributeName, $location, $name, $this->publicPrefix);
        }

        return null;
    }

    private function returnTypeError(ReflectionMethod $method, bool $html, string $location, string $attributeName): ?string
    {
        $error = $this->inspector->returnTypeError($method, $location, $attributeName, 'the type of the value printed in the template');
        if ($error !== null) {
            return $error;
        }

        if (EntryPointInspector::isVoid($method)) {
            return sprintf('%s on %s must return a value, not void.', $attributeName, $location);
        }

        $returnType = $method->getReturnType();
        $isString = $returnType instanceof ReflectionNamedType && $returnType->getName() === 'string' && !$returnType->allowsNull();
        if ($html && !$isString) {
            return sprintf('%s on %s has html: true and must return string.', $attributeName, $location);
        }

        return null;
    }

    private function parametersError(ReflectionMethod $method, TemplateCallableKind $kind, string $location, string $attributeName): ?string
    {
        if ($kind === TemplateCallableKind::Filter && $method->getNumberOfParameters() === 0) {
            return sprintf('%s on %s needs a first parameter that receives the filtered value.', $attributeName, $location);
        }

        foreach ($method->getParameters() as $parameter) {
            $parameterLocation = sprintf('parameter $%s of %s', $parameter->getName(), $location);

            $error = $this->inspector->parameterError($parameter, $parameterLocation, 'template ' . $kind->value . 's');
            if ($error !== null) {
                return $error;
            }

            $service = $this->resolver->injectedType($parameter);
            if ($service !== null) {
                return sprintf('%s: services are not injected into template %ss; inject %s through the constructor of %s.', $parameterLocation, $kind->value, $service, $method->getDeclaringClass()->getName());
            }
        }

        return null;
    }
}
