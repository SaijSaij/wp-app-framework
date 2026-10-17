<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Lifecycle;

use ReflectionClass;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\DependencyInjection\ServiceResolver;
use WebCodingCat\WpAppFramework\Manifest\EntryPointInspector;
use WebCodingCat\WpAppFramework\Project\ProjectKind;

/**
 * Collects and validates `#[OnActivation]`, `#[OnDeactivation]` and
 * `#[OnUninstall]` methods during the build and resolves the services they need.
 *
 * @phpstan-type LifecycleArgument array{param: string, service: class-string}
 * @phpstan-type LifecycleEntry array{class: class-string, method: string, arguments: list<LifecycleArgument>}
 * @phpstan-type LifecycleData array{
 *     activation: list<LifecycleEntry>,
 *     deactivation: list<LifecycleEntry>,
 *     uninstall: list<LifecycleEntry>
 * }
 */
#[NoService]
final class LifecycleCollector
{
    /** The file WordPress runs instead of the uninstall hook of a plugin, relative to the plugin. */
    public const string UNINSTALL_SCRIPT = 'uninstall.php';

    private const array ATTRIBUTES = [
        'activation' => [OnActivation::class, '#[OnActivation]'],
        'deactivation' => [OnDeactivation::class, '#[OnDeactivation]'],
        'uninstall' => [OnUninstall::class, '#[OnUninstall]'],
    ];

    /** @var list<string> */
    private array $errors = [];

    private readonly EntryPointInspector $inspector;

    public function __construct(
        private readonly ServiceResolver $resolver,
        private readonly ProjectKind $kind,
    ) {
        $this->inspector = new EntryPointInspector($resolver);
    }

    /**
     * The error for an `uninstall.php` in a plugin: WordPress runs it instead of the uninstall hook of the framework,
     * so the data of the plugin would silently stay.
     */
    public static function uninstallScriptError(string $pluginDirectory): ?string
    {
        $file = $pluginDirectory . '/' . self::UNINSTALL_SCRIPT;
        if (!is_file($file)) {
            return null;
        }

        return sprintf(
            '%s exists: WordPress runs it instead of the uninstall hook of the framework, which removes the data of the plugin. Move its code into a method with #[OnUninstall] and delete the file.',
            $file,
        );
    }

    /**
     * @param array<class-string, ReflectionClass<object>> $classes          All scanned classes.
     * @param string|null                                  $pluginDirectory The directory of a plugin, checked for an
     *                                                                       `uninstall.php`; null for themes and fixtures.
     * @return LifecycleData
     */
    public function collect(array $classes, ?string $pluginDirectory = null): array
    {
        $collected = ['activation' => [], 'deactivation' => [], 'uninstall' => []];
        $error = $pluginDirectory === null ? null : self::uninstallScriptError($pluginDirectory);
        if ($error !== null) {
            $this->errors[] = $error;
        }

        foreach ($classes as $name => $class) {
            foreach ($class->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $name) {
                    continue;
                }
                foreach (self::ATTRIBUTES as $phase => [$attributeClass, $attribute]) {
                    if ($method->getAttributes($attributeClass) === []) {
                        continue;
                    }

                    $location = sprintf('%s::%s()', $name, $method->getName());
                    $error = match (true) {
                        $phase === 'uninstall' && $this->kind !== ProjectKind::Plugin => sprintf(
                            '%s has #[OnUninstall], but only plugins can be uninstalled: WordPress loads no code of a theme it deletes, so the data of a %s stays.',
                            $location,
                            $this->kind->getLabel(),
                        ),
                        default => $this->inspector->serviceError($name, $location, $attribute)
                            ?? $this->inspector->publicInstanceMethodError($method, $location, $attribute)
                            ?? (EntryPointInspector::isVoid($method) ? null : sprintf('%s has %s and must return void.', $location, $attribute)),
                    };
                    if ($error !== null) {
                        $this->errors[] = $error;
                        continue;
                    }

                    $arguments = [];
                    $valid = true;
                    foreach ($method->getParameters() as $parameter) {
                        $parameterLocation = sprintf('parameter $%s of %s', $parameter->getName(), $location);
                        $parameterError = $this->inspector->parameterError($parameter, $parameterLocation, 'lifecycle methods');
                        $service = $this->resolver->injectedType($parameter);
                        if ($parameterError === null && $service === null) {
                            $parameterError = sprintf('%s: WordPress passes no arguments the method could use; the parameters of %s methods must be services.', $parameterLocation, $attribute);
                        }
                        if ($parameterError !== null || $service === null) {
                            $this->errors[] = (string) $parameterError;
                            $valid = false;
                            continue;
                        }
                        $arguments[] = ['param' => $parameter->getName(), 'service' => $service];
                    }
                    if (!$valid) {
                        continue;
                    }

                    $this->resolver->resolve($name);
                    foreach ($arguments as $argument) {
                        $this->resolver->resolve($argument['service']);
                    }
                    $collected[$phase][] = ['class' => $name, 'method' => $method->getName(), 'arguments' => $arguments];
                }
            }
        }

        foreach ($collected as $phase => $entries) {
            usort($entries, static fn(array $a, array $b): int => [$a['class'], $a['method']] <=> [$b['class'], $b['method']]);
            $collected[$phase] = $entries;
        }

        return $collected;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
