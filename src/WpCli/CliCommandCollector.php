<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\WpCli;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\DependencyInjection\ServiceResolver;
use WebCodingCat\WpAppFramework\Manifest\EntryPointInspector;
use WebCodingCat\WpAppFramework\Rest\Param;
use WebCodingCat\WpAppFramework\Value\PostReference;
use WebCodingCat\WpAppFramework\Value\TermReference;
use WebCodingCat\WpAppFramework\Value\UserReference;
use WebCodingCat\WpAppFramework\Value\ValueSchemaBuilder;

/**
 * Collects and validates `#[CliCommand]` services and their `#[CliSubcommand]`
 * methods during the build, derives the WP-CLI synopsis from the method
 * signatures and resolves the services they need.
 *
 * @phpstan-import-type CliArgument from CliCommandDefinition
 * @phpstan-import-type CliSubcommandEntry from CliCommandDefinition
 * @phpstan-import-type Value from \WebCodingCat\WpAppFramework\Value\ValueFactory
 */
#[NoService]
final class CliCommandCollector
{
    /**
     * The root commands WP-CLI 2.12.0 ships (`wp cli cmd-dump` without plugins and themes). A project namespace
     * equal to one of them would merge the project's commands into the WP-CLI command, so the build rejects it.
     * Kept up to date by the CLI test of theme-dev-test, which compares the list with the installed WP-CLI.
     */
    public const array WP_CLI_COMMANDS = [
        'cache', 'cap', 'cli', 'comment', 'config', 'core', 'cron', 'db', 'embed', 'eval',
        'eval-file', 'export', 'help', 'i18n', 'import', 'language', 'maintenance-mode', 'media', 'menu', 'network',
        'option', 'package', 'plugin', 'post', 'post-type', 'rewrite', 'role', 'scaffold', 'search-replace', 'server',
        'shell', 'sidebar', 'site', 'super-admin', 'taxonomy', 'term', 'theme', 'transient', 'user', 'widget',
    ];

    /**
     * Global parameters of WP-CLI (the runtime parameters of its config-spec.php), which never reach a command, and
     * `--help`, which shows the help instead of running the command.
     */
    public const array RESERVED_OPTIONS = [
        'allow-root', 'blog', 'color', 'context', 'debug', 'exec', 'help', 'http', 'path', 'prompt', 'quiet', 'require',
        'skip-packages', 'skip-plugins', 'skip-themes', 'ssh', 'url', 'user',
    ];

    /** Names of commands, subcommands, arguments and options. */
    private const string NAME_PATTERN = '~^[a-z][a-z0-9-]*$~';

    /** WP-CLI documents the short description as "80 char or less" (WP_CLI::add_command()). */
    private const int DESCRIPTION_LENGTH = 80;

    private const string SUPPORTED = 'use string, int, float, a backed enum or, for an option, bool';

    /** @var list<string> */
    private array $errors = [];

    private readonly EntryPointInspector $inspector;

    /**
     * @param non-empty-string $namespace Namespace of the project; commands are registered under `wp {namespace}`.
     */
    public function __construct(
        private readonly ServiceResolver $resolver,
        private readonly ValueSchemaBuilder $values,
        private readonly string $namespace,
    ) {
        $this->inspector = new EntryPointInspector($resolver);
    }

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     * @return list<CliCommandDefinition>
     */
    public function collect(array $classes): array
    {
        $commands = [];
        /** @var array<string, class-string> $registered Command name => class, to find duplicates. */
        $registered = [];

        foreach ($classes as $name => $class) {
            /** @var list<array{0: ReflectionMethod, 1: list<ReflectionAttribute<CliSubcommand>>}> $methods */
            $methods = [];
            foreach ($class->getMethods() as $method) {
                $attributes = $method->getAttributes(CliSubcommand::class);
                if ($attributes !== [] && $method->getDeclaringClass()->getName() === $name) {
                    $methods[] = [$method, $attributes];
                }
            }

            $commandAttributes = $class->getAttributes(CliCommand::class);
            if ($commandAttributes === []) {
                if ($methods !== []) {
                    $this->errors[] = sprintf('%s has #[CliSubcommand] methods, but no #[CliCommand] attribute naming their command group.', $name);
                }
                continue;
            }
            if ($methods === []) {
                $this->errors[] = sprintf('%s has #[CliCommand], but no #[CliSubcommand] methods.', $name);
                continue;
            }

            $command = $this->command($name, $commandAttributes[0]);
            if ($command === null) {
                continue;
            }
            if (isset($registered[$command->name])) {
                $this->errors[] = sprintf('Duplicate WP-CLI command "wp %s %s" in %s and %s.', $this->namespace, $command->name, $registered[$command->name], $name);
                continue;
            }
            $registered[$command->name] = $name;

            $subcommands = [];
            /** @var array<string, string> $subcommandNames Subcommand name => method, to find duplicates. */
            $subcommandNames = [];
            $services = [];
            $valid = true;
            foreach ($methods as [$method, $attributes]) {
                $location = sprintf('%s::%s()', $name, $method->getName());
                $parameters = $this->subcommand($method, $location, $services);
                if ($parameters === null) {
                    $valid = false;
                    continue;
                }

                foreach ($attributes as $attribute) {
                    $entry = $this->entry($attribute, $method, $location, $parameters);
                    if ($entry === null) {
                        $valid = false;
                        continue;
                    }
                    if (isset($subcommandNames[$entry['name']])) {
                        $this->errors[] = sprintf(
                            'Duplicate WP-CLI command "wp %s %s %s" in %s and %s.',
                            $this->namespace,
                            $command->name,
                            $entry['name'],
                            $subcommandNames[$entry['name']],
                            $location,
                        );
                        $valid = false;
                        continue;
                    }
                    $subcommandNames[$entry['name']] = $location;
                    $subcommands[] = $entry;
                }
            }
            if (!$valid) {
                continue;
            }

            /** @var non-empty-string $commandName Checked against the name pattern. */
            $commandName = $command->name;
            /** @var non-empty-string $description Checked to be not empty. */
            $description = $command->description;
            $commands[] = new CliCommandDefinition($commandName, $description, $name, $subcommands);

            $this->resolver->resolve($name);
            foreach (array_unique($services) as $service) {
                $this->resolver->resolve($service);
            }
        }

        if ($commands !== [] && in_array($this->namespace, self::WP_CLI_COMMANDS, true)) {
            $this->errors[] = sprintf(
                'The namespace "%s" (extra.wp-app-framework.namespace) is a command of WP-CLI, "wp %1$s"; WP-CLI commands of the project (%s) cannot be registered under it. Choose another namespace.',
                $this->namespace,
                implode(', ', array_map(static fn(CliCommandDefinition $command): string => $command->getClass(), $commands)),
            );
        }

        return $commands;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * The name of a parameter as WP-CLI argument or option: kebab-case, e.g. `dryRun` and `dry_run` become `dry-run`.
     */
    public static function cliName(string $parameter): string
    {
        return strtolower(str_replace('_', '-', (string) preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $parameter)));
    }

    /**
     * @param class-string                     $class
     * @param ReflectionAttribute<CliCommand> $attribute
     */
    private function command(string $class, ReflectionAttribute $attribute): ?CliCommand
    {
        $error = $this->inspector->serviceError($class, $class, '#[CliCommand]');
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }

        try {
            $command = $attribute->newInstance();
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[CliCommand] on %s: %s', $class, $exception->getMessage());

            return null;
        }

        $error = $this->nameError($command->name, sprintf('#[CliCommand] on %s', $class))
            ?? $this->descriptionError($command->description, sprintf('#[CliCommand] on %s', $class));
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }

        return $command;
    }

    /**
     * Checks a subcommand method and classifies its parameters.
     *
     * @param list<class-string> $services Receives the injected services.
     * @return list<CliArgument>|null Null if the method or a parameter is invalid.
     */
    private function subcommand(ReflectionMethod $method, string $location, array &$services): ?array
    {
        $error = $this->inspector->publicInstanceMethodError($method, $location, '#[CliSubcommand]');
        if ($error === null && !EntryPointInspector::isVoid($method)) {
            $error = sprintf('%s has #[CliSubcommand] and must return void; write output with a CliOutput parameter.', $location);
        }
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }

        $arguments = [];
        /** @var array<string, string> $names CLI name => parameter, to find duplicates. */
        $names = [];
        $optional = null;
        $index = 0;
        $valid = true;
        foreach ($method->getParameters() as $parameter) {
            $parameterLocation = sprintf('parameter $%s of %s', $parameter->getName(), $location);
            $argument = $this->argument($parameter, $parameterLocation, $optional, $index);
            if ($argument === null) {
                $valid = false;
                continue;
            }

            if ($argument['kind'] === 'service') {
                $services[] = $argument['class'];
            }
            if (isset($argument['name'])) {
                $name = $argument['name'];
                if (isset($names[$name])) {
                    $this->errors[] = sprintf('%s and parameter $%s both become "%s" in WP-CLI; rename one of them.', $parameterLocation, $names[$name], $name);
                    $valid = false;
                    continue;
                }
                $names[$name] = $parameter->getName();
            }
            if ($argument['kind'] === 'positional') {
                $index++;
            }
            $arguments[] = $argument;
        }

        return $valid ? $arguments : null;
    }

    /**
     * Classifies a parameter: the output, an injected service, a positional argument, an option or a flag.
     *
     * @param string|null $optional The first optional positional argument so far (`<name>`); updated. PHP itself makes
     *                              parameters with a default before a required one required, so only a variadic
     *                              argument can follow an optional one.
     * @return CliArgument|null
     */
    private function argument(ReflectionParameter $parameter, string $parameterLocation, ?string &$optional, int $index): ?array
    {
        $error = $this->inspector->parameterError($parameter, $parameterLocation, 'WP-CLI commands', true);
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }

        $isArgument = $parameter->getAttributes(Argument::class) !== [];
        $isOption = $parameter->getAttributes(Option::class) !== [];
        $type = $parameter->getType();
        $isOutput = $type instanceof ReflectionNamedType && $type->getName() === CliOutput::class;
        $service = $isOutput ? null : $this->resolver->injectedType($parameter);

        $error = match (true) {
            $parameter->isVariadic() && !$isArgument => 'is variadic; only an #[Argument] parameter can take several values',
            ($isOutput || $service !== null) && ($isArgument || $isOption) => sprintf(
                'is %s and must not have #[Argument] or #[Option]',
                $isOutput ? 'the CliOutput' : 'a service (' . $service . ')',
            ),
            $isArgument && $isOption => 'has #[Argument] and #[Option]; use one of them',
            default => null,
        };
        if ($error !== null) {
            $this->errors[] = sprintf('%s %s.', $parameterLocation, $error);

            return null;
        }

        if ($isOutput) {
            return ['param' => $parameter->getName(), 'kind' => 'output'];
        }
        if ($service !== null) {
            return ['param' => $parameter->getName(), 'kind' => 'service', 'class' => $service];
        }
        if (!$isArgument && !$isOption) {
            $this->errors[] = sprintf(
                '%s is neither a CliOutput nor a service; mark it #[Argument] for a positional argument or #[Option] for an option or flag.',
                $parameterLocation,
            );

            return null;
        }

        $field = $this->field($parameter, $parameterLocation);
        if ($field === null) {
            return null;
        }
        [$value, $schema] = $field;

        $name = self::cliName($parameter->getName());
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            $this->errors[] = sprintf(
                '%s becomes "%s" in WP-CLI, which is not a valid name: in kebab-case it must start with a letter and contain only letters, digits and "-".',
                $parameterLocation,
                $name,
            );

            return null;
        }

        $required = !$parameter->isDefaultValueAvailable() && !$parameter->isVariadic();
        $nullable = $value['nullable'];
        $isBool = $value['type'] === 'bool';

        if ($isArgument) {
            $label = '<' . $name . '>';
            $error = match (true) {
                $isBool => 'is bool; a positional argument cannot be a flag, mark it #[Option] instead',
                $nullable && $required => 'is nullable and needs a default value',
                $parameter->isVariadic() && $optional !== null => sprintf('is variadic and needs at least one value, but follows the optional argument %s; WP-CLI could not tell which value belongs to which argument', $optional),
                default => null,
            };
            if ($error !== null) {
                $this->errors[] = sprintf('%s %s.', $parameterLocation, $error);

                return null;
            }
            if (!$required && !$parameter->isVariadic()) {
                $optional ??= $label;
            }

            $argument = ['param' => $parameter->getName(), 'kind' => 'positional', 'name' => $name, 'index' => $index, 'variadic' => $parameter->isVariadic(), 'value' => $value, 'schema' => $schema];
            if ($parameter->isDefaultValueAvailable()) {
                $argument['default'] = ValueSchemaBuilder::export($parameter->getDefaultValue());
            }

            return $argument;
        }

        $error = match (true) {
            in_array($name, self::RESERVED_OPTIONS, true) => sprintf('becomes the option --%s, which WP-CLI reserves for itself (a global parameter or --help); rename the parameter', $name),
            str_starts_with($name, 'no-') => sprintf('becomes the option --%s; WP-CLI reads --no-{name} as {name} set to false, so an option cannot start with "no-"', $name),
            $isBool && ($nullable || !$parameter->isDefaultValueAvailable() || $parameter->getDefaultValue() !== false) => sprintf('is a flag and must be declared as bool $%s = false', $parameter->getName()),
            $nullable && $required => 'is nullable and needs a default value',
            default => null,
        };
        if ($error !== null) {
            $this->errors[] = sprintf('%s %s.', $parameterLocation, $error);

            return null;
        }

        if ($isBool) {
            return ['param' => $parameter->getName(), 'kind' => 'flag', 'name' => $name];
        }

        $argument = ['param' => $parameter->getName(), 'kind' => 'option', 'name' => $name, 'value' => $value, 'schema' => $schema];
        if (!$required) {
            $argument['default'] = ValueSchemaBuilder::export($parameter->getDefaultValue());
        }

        return $argument;
    }

    /**
     * The value description and schema of an argument or option.
     *
     * @return array{0: Value, 1: array<string, mixed>}|null
     */
    private function field(ReflectionParameter $parameter, string $parameterLocation): ?array
    {
        if ($parameter->getAttributes(Param::class) !== []) {
            $this->errors[] = sprintf('%s has #[Param], which adds schema keywords to REST arguments, not to WP-CLI arguments.', $parameterLocation);

            return null;
        }
        foreach ([PostReference::class, TermReference::class, UserReference::class] as $reference) {
            if ($parameter->getAttributes($reference) !== []) {
                $this->errors[] = sprintf('%s has #[%s], which marks fields of blocks and settings groups, not WP-CLI arguments; declare the ID as int.', $parameterLocation, substr(strrchr($reference, '\\') ?: $reference, 1));

                return null;
            }
        }

        $type = $parameter->getType();
        if ($type === null) {
            $this->errors[] = sprintf('%s has no type; %s.', $parameterLocation, self::SUPPORTED);

            return null;
        }
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;
        $supported = $typeName !== null && (in_array($typeName, ['string', 'int', 'float', 'bool'], true) || enum_exists($typeName));
        if (!$supported) {
            $this->errors[] = sprintf(
                '%s: type %s is not supported in WP-CLI commands; %s, optionally nullable.%s',
                $parameterLocation,
                $type,
                self::SUPPORTED,
                $typeName === 'array' ? ' For several values, declare a variadic #[Argument], e.g. int ...$ids.' : '',
            );

            return null;
        }

        $field = $this->values->parameter($parameter, $parameterLocation);
        if ($field === null) {
            return null;
        }

        return [$field['value'], $field['schema']];
    }

    /**
     * Builds the subcommand entry of one #[CliSubcommand] attribute, with the synopsis WP-CLI shows and validates.
     *
     * @param ReflectionAttribute<CliSubcommand> $attribute
     * @param list<CliArgument>                  $arguments
     * @return CliSubcommandEntry|null
     */
    private function entry(ReflectionAttribute $attribute, ReflectionMethod $method, string $location, array $arguments): ?array
    {
        try {
            $subcommand = $attribute->newInstance();
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[CliSubcommand] on %s: %s', $location, $exception->getMessage());

            return null;
        }

        $error = $this->nameError($subcommand->name, sprintf('#[CliSubcommand] on %s', $location))
            ?? $this->descriptionError($subcommand->description, sprintf('#[CliSubcommand] on %s', $location));
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }

        $descriptions = [];
        foreach ($method->getParameters() as $parameter) {
            $marker = $parameter->getAttributes(Argument::class)[0] ?? $parameter->getAttributes(Option::class)[0] ?? null;
            if ($marker !== null) {
                $markerArguments = $marker->getArguments();
                $description = $markerArguments[0] ?? $markerArguments['description'] ?? '';
                $descriptions[$parameter->getName()] = is_string($description) ? $description : '';
            }
        }

        $synopsis = [];
        foreach (['positional', 'option', 'flag'] as $kind) {
            foreach ($arguments as $argument) {
                if ($argument['kind'] !== $kind) {
                    continue;
                }
                $synopsis[] = self::synopsis($argument, $descriptions[$argument['param']] ?? '');
            }
        }

        /** @var non-empty-string $name Checked against the name pattern. */
        $name = $subcommand->name;
        /** @var non-empty-string $description Checked to be not empty. */
        $description = $subcommand->description;

        return [
            'name' => $name,
            'description' => $description,
            'longDescription' => $subcommand->longDescription,
            'method' => $method->getName(),
            'synopsis' => $synopsis,
            'arguments' => $arguments,
        ];
    }

    /**
     * One entry of the structured synopsis of WP_CLI::add_command(): WP-CLI renders it as usage line and `## OPTIONS`.
     *
     * @param CliArgument $argument
     * @return array<string, mixed>
     */
    private static function synopsis(array $argument, string $description): array
    {
        $entry = match ($argument['kind']) {
            'positional' => [
                'type' => 'positional',
                'name' => $argument['name'],
                'optional' => array_key_exists('default', $argument),
                'repeating' => $argument['variadic'],
            ],
            'option' => [
                'type' => 'assoc',
                'name' => $argument['name'],
                'optional' => array_key_exists('default', $argument),
            ],
            'flag' => ['type' => 'flag', 'name' => $argument['name'], 'optional' => true],
            default => [],
        };
        if ($description !== '') {
            $entry['description'] = $description;
        }
        if ($argument['kind'] === 'positional' || $argument['kind'] === 'option') {
            $default = $argument['default'] ?? null;
            if (is_scalar($default) && $default !== '') {
                $entry['default'] = $default;
            }
            if ($argument['value']['type'] === 'enum' && isset($argument['schema']['enum']) && is_array($argument['schema']['enum'])) {
                $entry['options'] = array_values(array_filter($argument['schema']['enum'], static fn(mixed $option): bool => $option !== null));
            }
        }

        return $entry;
    }

    private function nameError(string $name, string $location): ?string
    {
        if (preg_match(self::NAME_PATTERN, $name) === 1) {
            return null;
        }

        return sprintf('%s has the invalid name "%s": start with a lowercase letter, then use lowercase letters, digits and "-".', $location, $name);
    }

    private function descriptionError(string $description, string $location): ?string
    {
        return match (true) {
            trim($description) === '' => sprintf('%s has an empty description; WP-CLI shows it in the list of commands.', $location),
            str_contains($description, "\n") || str_contains($description, "\r") => sprintf('%s has a description with a line break; put further text into longDescription of #[CliSubcommand].', $location),
            mb_strlen($description) > self::DESCRIPTION_LENGTH => sprintf('%s has a description of %d characters; WP-CLI allows at most %d.', $location, mb_strlen($description), self::DESCRIPTION_LENGTH),
            default => null,
        };
    }
}
