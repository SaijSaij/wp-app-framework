<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Rest;

use BackedEnum;
use LogicException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Manifest\EntryPointInspector;
use Saij\WpAppFramework\Rest\Permission\Callback;
use Saij\WpAppFramework\Rest\Permission\Capability;
use Saij\WpAppFramework\Rest\Permission\PublicAccess;
use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\ValueSchemaBuilder;
use Throwable;

/**
 * Collects and validates `#[RestRoute]` methods during the build, derives the
 * argument schemas from the method signatures and resolves the services they need.
 *
 * @phpstan-import-type RestArgument from RestRouteDefinition
 * @phpstan-import-type RestPermission from RestRouteDefinition
 * @phpstan-import-type Value from \Saij\WpAppFramework\Value\ValueFactory
 * @phpstan-type Parameter array{
 *     param: string,
 *     kind: 'service'|'request'|'param'|'url',
 *     class?: class-string,
 *     value?: Value,
 *     enum?: class-string<BackedEnum>|null,
 *     required?: bool,
 *     type?: string,
 *     schema?: array<string, mixed>
 * }
 */
#[NoService]
final class RestRouteCollector
{
    private const string REQUEST_CLASS = 'WP_REST_Request';

    private const string PERMISSION_HINT = "new PublicAccess(), new Capability('…') or new Callback(self::method(...))";

    /** @var list<string> */
    private array $errors = [];

    private readonly EntryPointInspector $inspector;

    public function __construct(
        private readonly ServiceResolver $resolver,
        private readonly ValueSchemaBuilder $values,
    ) {
        $this->inspector = new EntryPointInspector($resolver);
    }

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     * @return list<RestRouteDefinition>
     */
    public function collect(array $classes): array
    {
        $routes = [];
        /** @var array<string, string> $registered Route key => source, to find duplicates. */
        $registered = [];

        foreach ($classes as $name => $class) {
            $controller = false;

            foreach ($class->getMethods() as $method) {
                $attributes = $method->getAttributes(RestRoute::class);
                if ($attributes === [] || $method->getDeclaringClass()->getName() !== $name) {
                    continue;
                }

                $location = sprintf('%s::%s()', $name, $method->getName());

                if ($controller === false) {
                    $controller = $this->controller($class);
                }
                if ($controller === null) {
                    continue;
                }

                $error = $this->inspector->serviceError($name, $location, '#[RestRoute]')
                    ?? $this->inspector->publicInstanceMethodError($method, $location, '#[RestRoute]')
                    ?? $this->inspector->returnTypeError($method, $location, '#[RestRoute]', 'void for 204 No Content, otherwise the type of the response data');
                if ($error !== null) {
                    $this->errors[] = $error;
                    continue;
                }

                $parameters = $this->parameters($method, $location);
                if ($parameters === null) {
                    continue;
                }

                $services = [];
                foreach ($attributes as $attribute) {
                    $arguments = $attribute->getArguments();
                    if (!array_key_exists(2, $arguments) && !array_key_exists('permission', $arguments)) {
                        $this->errors[] = sprintf('#[RestRoute] on %s has no permission. Pass %s as permission.', $location, self::PERMISSION_HINT);
                        continue;
                    }

                    try {
                        $route = $attribute->newInstance();
                    } catch (Throwable $exception) {
                        $this->errors[] = sprintf('Invalid #[RestRoute] on %s: %s', $location, $exception->getMessage());
                        continue;
                    }

                    $definition = $this->route($route, $controller, $parameters, $classes, $name, $method, $location, $services);
                    if ($definition === null) {
                        continue;
                    }

                    foreach ($definition->getMethods() as $httpMethod) {
                        $key = $httpMethod . ' /' . $definition->getNamespace() . $definition->getRoute();
                        if (isset($registered[$key])) {
                            $this->errors[] = sprintf('Duplicate REST route %s in %s and %s.', $key, $registered[$key], $location);
                            continue 2;
                        }
                        $registered[$key] = $location;
                    }

                    $routes[] = $definition;
                }

                $this->resolver->resolve($name);
                foreach ($parameters as $parameter) {
                    if ($parameter['kind'] === 'service') {
                        $services[] = $parameter['class'];
                    }
                }
                foreach (array_unique($services) as $service) {
                    $this->resolver->resolve($service);
                }
            }
        }

        return $routes;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function controller(ReflectionClass $class): ?RestController
    {
        $name = $class->getName();
        $attributes = $class->getAttributes(RestController::class);
        if ($attributes === []) {
            $this->errors[] = sprintf('%s has #[RestRoute] methods, but no #[RestController] attribute defining their REST namespace.', $name);

            return null;
        }

        try {
            $controller = $attributes[0]->newInstance();
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[RestController] on %s: %s', $name, $exception->getMessage());

            return null;
        }

        $valid = true;
        if (preg_match('~^[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)*$~', $controller->namespace) !== 1) {
            $this->errors[] = sprintf(
                '#[RestController] on %s has the invalid namespace "%s": use letters, digits, ".", "_", "-" and "/" without leading or trailing slash, e.g. "sections/v1".',
                $name,
                $controller->namespace,
            );
            $valid = false;
        }
        if ($controller->base !== '' && (!str_starts_with($controller->base, '/') || str_ends_with($controller->base, '/'))) {
            $this->errors[] = sprintf('#[RestController] on %s has the invalid base path "%s": it must be empty or start with "/" and not end with "/".', $name, $controller->base);
            $valid = false;
        }

        return $valid ? $controller : null;
    }

    /**
     * Classifies the method parameters and derives the schema of the request arguments.
     *
     * @return list<Parameter>|null Null if a parameter is invalid.
     */
    private function parameters(ReflectionMethod $method, string $location): ?array
    {
        $parameters = [];
        $valid = true;

        foreach ($method->getParameters() as $parameter) {
            $parameterLocation = sprintf('parameter $%s of %s', $parameter->getName(), $location);

            $error = $this->inspector->parameterError($parameter, $parameterLocation, 'REST routes');
            if ($error !== null) {
                $this->errors[] = $error;
                $valid = false;
                continue;
            }

            $kind = $this->kind($parameter);
            if ($kind !== null) {
                if ($parameter->getAttributes(Param::class) !== []) {
                    $this->errors[] = sprintf('%s has #[Param], but is not a request argument.', $parameterLocation);
                    $valid = false;
                    continue;
                }
                $parameters[] = $kind;
                continue;
            }

            $argument = $this->argument($parameter, $parameterLocation);
            if ($argument === null) {
                $valid = false;
                continue;
            }
            $parameters[] = $argument;
        }

        return $valid ? $parameters : null;
    }

    /**
     * An injected service or the request, or null for a request argument.
     *
     * @return Parameter|null
     */
    private function kind(ReflectionParameter $parameter): ?array
    {
        $service = $this->resolver->injectedType($parameter);
        if ($service !== null) {
            return ['param' => $parameter->getName(), 'kind' => 'service', 'class' => $service];
        }

        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && $type->getName() === self::REQUEST_CLASS && !$type->allowsNull()) {
            return ['param' => $parameter->getName(), 'kind' => 'request'];
        }

        return null;
    }

    /**
     * @return Parameter|null
     */
    private function argument(ReflectionParameter $parameter, string $parameterLocation): ?array
    {
        $field = $this->values->parameter($parameter, $parameterLocation);
        if ($field === null) {
            return null;
        }

        $value = $field['value'];
        if ($value['type'] === 'object') {
            $this->errors[] = sprintf(
                '%s: a single value class is not supported as REST argument (a class type without #[ListOf] is injected as service); use #[ListOf(%s::class)] array for a list.',
                $parameterLocation,
                $value['class'],
            );

            return null;
        }

        $nullable = $value['nullable'];
        $required = !$parameter->isDefaultValueAvailable();
        if ($nullable && $required) {
            $this->errors[] = sprintf('%s is nullable and needs a default value: WordPress treats null as a missing argument.', $parameterLocation);

            return null;
        }

        $schema = $field['schema'];
        if ($required) {
            $schema['required'] = true;
        } else {
            $default = ValueSchemaBuilder::export($parameter->getDefaultValue());
            if ($default !== null) {
                $schema['default'] = $default;
            }
        }

        $schemaKind = match ($value['type']) {
            'enum' => 'enum',
            'list' => 'array',
            default => (array) $schema['type'],
        };
        $keywords = $this->keywords($parameter, $parameterLocation, is_array($schemaKind) ? $schemaKind[0] : $schemaKind);
        if ($keywords === null) {
            return null;
        }
        if (isset($keywords['format'], $schema['format'])) {
            $this->errors[] = sprintf('%s has #[Url] and #[Param(format:)]; use one of them.', $parameterLocation);

            return null;
        }

        return [
            'param' => $parameter->getName(),
            'kind' => 'param',
            'value' => $value,
            'enum' => $value['type'] === 'enum' ? $value['class'] : null,
            'required' => $required,
            'type' => (string) $parameter->getType(),
            'schema' => [...$schema, ...$keywords],
        ];
    }

    /**
     * Schema keywords from #[Param], checked against the schema type.
     *
     * @return array<string, mixed>|null
     */
    private function keywords(ReflectionParameter $parameter, string $parameterLocation, string $schemaType): ?array
    {
        $attributes = $parameter->getAttributes(Param::class);
        if ($attributes === []) {
            return [];
        }

        try {
            $param = $attributes[0]->newInstance();
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[Param] on %s: %s', $parameterLocation, $exception->getMessage());

            return null;
        }

        $keywords = array_filter([
            'description' => $param->description,
            'minimum' => $param->minimum,
            'maximum' => $param->maximum,
            'minLength' => $param->minLength,
            'maxLength' => $param->maxLength,
            'pattern' => $param->pattern,
            'format' => $param->format?->value,
        ], static fn (mixed $value): bool => $value !== null);

        $allowed = match ($schemaType) {
            'integer', 'number' => ['description', 'minimum', 'maximum'],
            'string' => ['description', 'minLength', 'maxLength', 'pattern', 'format'],
            default => ['description'],
        };
        $typeLabel = match ($schemaType) {
            'enum' => 'a backed enum',
            'array' => 'list',
            default => $schemaType,
        };

        $valid = true;
        foreach (array_keys($keywords) as $keyword) {
            if (!in_array($keyword, $allowed, true)) {
                $this->errors[] = sprintf('#[Param] on %s: %s does not apply to %s arguments.', $parameterLocation, $keyword, $typeLabel);
                $valid = false;
            }
        }

        if (isset($keywords['pattern']) && $valid) {
            // WordPress matches patterns like this in rest_validate_json_schema_pattern().
            $regex = '#' . str_replace('#', '\\#', $keywords['pattern']) . '#u';
            $warning = '';
            set_error_handler(static function (int $level, string $message) use (&$warning): bool {
                $warning = $message;

                return true;
            });
            try {
                $compiles = preg_match($regex, '') !== false;
            } finally {
                restore_error_handler();
            }
            if (!$compiles) {
                $this->errors[] = sprintf('#[Param] on %s: pattern "%s" is not a valid regular expression: %s', $parameterLocation, $keywords['pattern'], $warning);
                $valid = false;
            }
        }

        return $valid ? $keywords : null;
    }

    /**
     * @param list<Parameter>                              $parameters
     * @param array<class-string, ReflectionClass<object>> $classes
     * @param class-string                                 $class
     * @param list<class-string>                           $services   Receives the services of a permission callback.
     */
    private function route(
        RestRoute $route,
        RestController $controller,
        array $parameters,
        array $classes,
        string $class,
        ReflectionMethod $method,
        string $location,
        array &$services,
    ): ?RestRouteDefinition {
        $methods = $this->httpMethods($route, $location);

        if ($route->path !== '' && !str_starts_with($route->path, '/')) {
            $this->errors[] = sprintf('#[RestRoute] on %s has the invalid path "%s": it must be empty or start with "/".', $location, $route->path);

            return null;
        }

        $path = $controller->base . $route->path;
        if ($path === '') {
            $this->errors[] = sprintf('#[RestRoute] on %s has an empty route: set a path or a #[RestController] base path.', $location);

            return null;
        }

        $compiled = $this->compile($path, $parameters, $location);
        $permission = $this->permission($route, $compiled === null ? null : $compiled['parameters'], $classes, $location, $services);

        if ($methods === null || $compiled === null || $permission === null) {
            return null;
        }

        $args = [];
        $arguments = [];
        foreach ($compiled['parameters'] as $parameter) {
            $arguments[] = self::runtimeArgument($parameter);
            if (isset($parameter['schema'])) {
                $args[$parameter['param']] = $parameter['schema'];
            }
        }

        return new RestRouteDefinition(
            $controller->namespace,
            $compiled['regex'],
            $methods,
            $args,
            $permission,
            $class,
            $method->getName(),
            $arguments,
            EntryPointInspector::isVoid($method),
        );
    }

    /**
     * @return list<string>|null
     */
    private function httpMethods(RestRoute $route, string $location): ?array
    {
        $methods = $route->methods instanceof HttpMethod ? [$route->methods] : $route->methods;

        if ($methods === []) {
            $this->errors[] = sprintf('#[RestRoute] on %s has no HTTP method.', $location);

            return null;
        }

        $names = [];
        foreach ($methods as $method) {
            if (!$method instanceof HttpMethod) {
                $this->errors[] = sprintf('#[RestRoute] on %s: HTTP methods must be HttpMethod cases, %s given.', $location, get_debug_type($method));

                return null;
            }
            if (in_array($method->value, $names, true)) {
                $this->errors[] = sprintf('#[RestRoute] on %s lists the HTTP method %s twice.', $location, $method->value);

                return null;
            }
            $names[] = $method->value;
        }

        return $names;
    }

    /**
     * Turns `{name}` placeholders into named groups and marks their parameters as URL parameters.
     *
     * @param list<Parameter> $parameters
     * @return array{regex: string, parameters: list<Parameter>}|null
     */
    private function compile(string $path, array $parameters, string $location): ?array
    {
        $byName = [];
        foreach ($parameters as $index => $parameter) {
            $byName[$parameter['param']] = $index;
        }

        $parts = preg_split('~\{([A-Za-z_][A-Za-z0-9_]*)\}~', $path, -1, PREG_SPLIT_DELIM_CAPTURE);
        assert(is_array($parts));

        $regex = '';
        $placeholders = [];
        $valid = true;
        foreach ($parts as $position => $part) {
            if ($position % 2 === 0) {
                if (preg_match('~[^A-Za-z0-9._\~/-]~', $part) === 1) {
                    $this->errors[] = sprintf(
                        '#[RestRoute] on %s: route "%s" contains characters other than letters, digits, ".", "_", "~", "-", "/" and {name} placeholders.',
                        $location,
                        $path,
                    );
                    $valid = false;
                }
                $regex .= preg_quote($part, '@');
                continue;
            }

            if (in_array($part, $placeholders, true)) {
                $this->errors[] = sprintf('#[RestRoute] on %s: route "%s" contains the placeholder {%s} twice.', $location, $path, $part);
                $valid = false;
                continue;
            }
            $placeholders[] = $part;

            $index = $byName[$part] ?? null;
            if ($index === null || $parameters[$index]['kind'] !== 'param') {
                $this->errors[] = sprintf('#[RestRoute] on %s: placeholder {%s} in route "%s" has no request argument parameter $%s.', $location, $part, $path, $part);
                $valid = false;
                continue;
            }

            $parameter = $parameters[$index];
            $parameterLocation = sprintf('parameter $%s of %s', $part, $location);
            if (!($parameter['required'] ?? false)) {
                $this->errors[] = sprintf('%s is a path parameter and must be required: no default value and not nullable.', $parameterLocation);
                $valid = false;
                continue;
            }

            $pattern = match (true) {
                ($parameter['enum'] ?? null) !== null => '(?:' . implode('|', array_map(
                    static fn (BackedEnum $case): string => preg_quote((string) $case->value, '@'),
                    $parameter['enum']::cases(),
                )) . ')',
                ($parameter['type'] ?? null) === 'int' => '\d+',
                ($parameter['type'] ?? null) === 'string' => '[^/]+',
                default => null,
            };
            if ($pattern === null) {
                $this->errors[] = sprintf('%s is a path parameter and must be int, string or a backed enum.', $parameterLocation);
                $valid = false;
                continue;
            }

            $regex .= sprintf('(?P<%s>%s)', $part, $pattern);
            $parameters[$index]['kind'] = 'url';
        }

        return $valid ? ['regex' => $regex, 'parameters' => $parameters] : null;
    }

    /**
     * @param list<Parameter>|null                         $parameters Route parameters, null if the route is invalid.
     * @param array<class-string, ReflectionClass<object>> $classes
     * @param list<class-string>                           $services
     * @return RestPermission|null
     */
    private function permission(RestRoute $route, ?array $parameters, array $classes, string $location, array &$services): ?array
    {
        $permission = $route->permission;

        if ($permission instanceof PublicAccess) {
            return ['type' => 'public'];
        }

        if ($permission instanceof Capability) {
            if ($permission->getCapability() === '') {
                $this->errors[] = sprintf('#[RestRoute] on %s has an empty capability.', $location);

                return null;
            }

            return ['type' => 'capability', 'capability' => $permission->getCapability()];
        }

        if (!$permission instanceof Callback) {
            $this->errors[] = sprintf('#[RestRoute] on %s uses %s as permission; only %s are supported.', $location, $permission::class, self::PERMISSION_HINT);

            return null;
        }

        $function = new ReflectionFunction($permission->getCallback());
        $scope = $function->getClosureScopeClass();
        if (str_starts_with($function->getName(), '{closure') || $scope === null || !$scope->hasMethod($function->getName())) {
            $this->errors[] = sprintf(
                '#[RestRoute] on %s: the permission callback must be a first-class callable of a public static method, e.g. new Callback(self::canEdit(...)); anonymous closures are not supported.',
                $location,
            );

            return null;
        }

        $callback = $scope->getMethod($function->getName());
        $callbackClass = $scope->getName();
        $callbackLocation = sprintf('%s::%s()', $callbackClass, $callback->getName());

        if (!isset($classes[$callbackClass])) {
            $this->errors[] = sprintf('Permission callback %s of %s is outside the scanned PSR-4 directories of the theme and the framework.', $callbackLocation, $location);

            return null;
        }

        $returnType = $callback->getReturnType();
        if (!$callback->isPublic() || !$callback->isStatic() || !$returnType instanceof ReflectionNamedType || $returnType->getName() !== 'bool' || $returnType->allowsNull()) {
            $this->errors[] = sprintf('Permission callback %s of %s must be public and static and return bool.', $callbackLocation, $location);

            return null;
        }

        if ($parameters === null) {
            return null;
        }

        $routeArguments = [];
        foreach ($parameters as $parameter) {
            if ($parameter['kind'] === 'param' || $parameter['kind'] === 'url') {
                $routeArguments[$parameter['param']] = $parameter;
            }
        }

        $arguments = [];
        $valid = true;
        foreach ($callback->getParameters() as $parameter) {
            $parameterLocation = sprintf('parameter $%s of permission callback %s', $parameter->getName(), $callbackLocation);

            $error = $this->inspector->parameterError($parameter, $parameterLocation, 'REST routes');
            if ($error !== null) {
                $this->errors[] = $error;
                $valid = false;
                continue;
            }

            $kind = $this->kind($parameter);
            if ($kind !== null) {
                if ($kind['kind'] === 'service') {
                    $services[] = $kind['class'];
                }
                $arguments[] = self::runtimeArgument($kind);
                continue;
            }

            $routeArgument = $routeArguments[$parameter->getName()] ?? null;
            $type = self::declaredType($parameter);
            $routeType = $routeArgument === null ? null : self::declaredType($routeArgument);
            if ($routeArgument === null || $routeType !== $type) {
                $this->errors[] = sprintf(
                    '%s must be a service, WP_REST_Request or a request argument of %s with the same name and type (%s).',
                    $parameterLocation,
                    $location,
                    $routeArgument === null ? 'no such argument' : 'the route declares ' . $routeType,
                );
                $valid = false;
                continue;
            }
            if (!$routeArgument['required'] && !$parameter->isDefaultValueAvailable()) {
                $this->errors[] = sprintf('%s needs a default value, because the request argument is optional.', $parameterLocation);
                $valid = false;
                continue;
            }

            $arguments[] = self::runtimeArgument($routeArgument);
        }

        if (!$valid) {
            return null;
        }

        return ['type' => 'callback', 'class' => $callbackClass, 'method' => $callback->getName(), 'arguments' => $arguments];
    }

    /**
     * @param Parameter $parameter
     * @return RestArgument
     */
    private static function runtimeArgument(array $parameter): array
    {
        return match ($parameter['kind']) {
            'service' => ['param' => $parameter['param'], 'kind' => 'service', 'class' => (string) ($parameter['class'] ?? '')],
            'request' => ['param' => $parameter['param'], 'kind' => 'request'],
            default => [
                'param' => $parameter['param'],
                'kind' => $parameter['kind'],
                'value' => $parameter['value'] ?? throw new LogicException(sprintf('Request argument $%s has no value description.', $parameter['param'])),
            ],
        };
    }

    /**
     * The PHP type of a parameter, including the item class of a list, e.g. "array<Level>".
     *
     * @param ReflectionParameter|Parameter $parameter
     */
    private static function declaredType(ReflectionParameter|array $parameter): string
    {
        if (is_array($parameter)) {
            $value = $parameter['value'] ?? null;
            $type = $parameter['type'] ?? '';

            return $value !== null && $value['type'] === 'list' && isset($value['items']['class'])
                ? sprintf('%s<%s>', $type, $value['items']['class'])
                : $type;
        }

        $type = (string) $parameter->getType();
        $listOf = $parameter->getAttributes(ListOf::class)[0] ?? null;
        if ($listOf === null) {
            return $type;
        }
        $arguments = $listOf->getArguments();

        return sprintf('%s<%s>', $type, (string) ($arguments[0] ?? $arguments['class'] ?? ''));
    }
}
