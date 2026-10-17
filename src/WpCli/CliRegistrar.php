<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\WpCli;

use Throwable;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Project\ProjectKind;
use WebCodingCat\WpAppFramework\Value\ValueFactory;
use WP_CLI;
use WP_CLI\ExitException;

/**
 * Registers the WP-CLI commands of the manifest, `wp {namespace} {command} {subcommand}`;
 * the boot calls it only when WP-CLI runs.
 *
 * Services are resolved only when a command runs. WP-CLI checks the form of
 * the arguments against the synopsis; the values are validated and converted
 * here, like REST arguments. A CliException becomes a WP-CLI error with its
 * exit code; any other exception is logged and, when debugging is enabled,
 * rethrown, otherwise reported as error with exit code 1.
 *
 * @internal
 *
 * @phpstan-import-type CliCommandEntry from Manifest
 * @phpstan-import-type CliSubcommandEntry from CliCommandDefinition
 * @phpstan-import-type CliArgument from CliCommandDefinition
 * @phpstan-import-type Value from ValueFactory
 */
#[NoService]
final readonly class CliRegistrar
{
    private const array FLAG_SCHEMA = ['type' => 'boolean'];

    /**
     * @param non-empty-string $namespace
     * @param non-empty-string $slug
     */
    public function __construct(
        private Container $container,
        private ValueFactory $values,
        private bool $debug,
        private string $namespace,
        private ProjectKind $kind,
        private string $slug,
    ) {}

    /**
     * Registers the namespace and each command as group, then the subcommands. If the namespace is already a
     * command (of WP-CLI, a plugin or a package), WP-CLI would merge both silently: the commands of this
     * instance are left out with a warning instead.
     *
     * @param list<CliCommandEntry> $commands
     */
    public function register(array $commands): void
    {
        $path = [$this->namespace];
        if (WP_CLI::get_root_command()->find_subcommand($path) !== false) {
            WP_CLI::warning(sprintf(
                'The WP-CLI commands of the %s %s are not registered: "wp %s" is already a command. Change the namespace (extra.wp-app-framework.namespace) of the %1$s.',
                $this->kind->getLabel(),
                $this->slug,
                $this->namespace,
            ));

            return;
        }

        WP_CLI::add_command($this->namespace, self::group(), [
            'shortdesc' => sprintf('Commands of the %s %s.', $this->kind->getLabel(), $this->slug),
            'longdesc' => '',
        ]);
        $registrar = $this;
        foreach ($commands as $command) {
            $name = $this->namespace . ' ' . $command['name'];
            WP_CLI::add_command($name, self::group(), ['shortdesc' => $command['description'], 'longdesc' => '']);

            foreach ($command['subcommands'] as $subcommand) {
                WP_CLI::add_command(
                    $name . ' ' . $subcommand['name'],
                    static function (array $args, array $assocArgs) use ($registrar, $command, $subcommand): void {
                        /** @var array<int, mixed> $args */
                        /** @var array<string, mixed> $assocArgs */
                        $registrar->invoke($command, $subcommand, $args, $assocArgs);
                    },
                    [
                        'shortdesc' => $subcommand['description'],
                        'longdesc' => $subcommand['longDescription'],
                        'synopsis' => $subcommand['synopsis'],
                    ],
                );
            }
        }
    }

    /**
     * Runs a subcommand with the arguments WP-CLI passes.
     *
     * @param CliCommandEntry            $command
     * @param CliSubcommandEntry         $subcommand
     * @param array<int, mixed>          $args      Positional arguments.
     * @param array<string, mixed> $assocArgs Options and flags.
     */
    public function invoke(array $command, array $subcommand, array $args, array $assocArgs): void
    {
        try {
            $arguments = $this->arguments($subcommand['arguments'], $args, $assocArgs);
            $this->container->get($command['class'])->{$subcommand['method']}(...$arguments);
        } catch (ExitException $exception) {
            // WP_CLI::error() and halt() throw it inside WP_CLI::runcommand(); WP-CLI handles it.
            throw $exception;
        } catch (CliException $exception) {
            WP_CLI::error($exception->getMessage(), $exception->getExitCode());
        } catch (Throwable $exception) {
            error_log(sprintf(
                'wp-app-framework: WP-CLI command "wp %s" (%s::%s()) failed: %s: %s in %s:%d',
                $this->namespace . ' ' . $command['name'] . ' ' . $subcommand['name'],
                $command['class'],
                $subcommand['method'],
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ));
            if ($this->debug) {
                throw $exception;
            }

            WP_CLI::error(sprintf('Command failed: %s: %s', $exception::class, $exception->getMessage()));
        }
    }

    /**
     * The method arguments in declaration order, the values of a variadic argument last.
     *
     * @param list<CliArgument>          $arguments
     * @param array<int, mixed>          $args
     * @param array<string, mixed> $assocArgs
     * @return list<mixed>
     */
    private function arguments(array $arguments, array $args, array $assocArgs): array
    {
        $values = [];
        foreach ($arguments as $argument) {
            switch ($argument['kind']) {
                case 'output':
                    $values[] = new CliOutput();
                    break;
                case 'service':
                    $values[] = $this->container->get($argument['class']);
                    break;
                case 'flag':
                    $label = '--' . $argument['name'];
                    $values[] = array_key_exists($argument['name'], $assocArgs)
                        ? $this->convert(['type' => 'bool', 'nullable' => false], self::FLAG_SCHEMA, $assocArgs[$argument['name']], $argument['name'], $label)
                        : false;
                    break;
                case 'option':
                    $label = '--' . $argument['name'];
                    $values[] = array_key_exists($argument['name'], $assocArgs)
                        ? $this->convert($argument['value'], $argument['schema'], $assocArgs[$argument['name']], $argument['name'], $label)
                        : $this->default($argument, $label);
                    break;
                case 'positional':
                    $label = '<' . $argument['name'] . '>';
                    if ($argument['variadic']) {
                        foreach (array_slice($args, $argument['index']) as $raw) {
                            $values[] = $this->convert($argument['value'], $argument['schema'], $raw, $argument['name'], $label);
                        }
                        break;
                    }
                    $values[] = array_key_exists($argument['index'], $args)
                        ? $this->convert($argument['value'], $argument['schema'], $args[$argument['index']], $argument['name'], $label)
                        : $this->default($argument, $label);
                    break;
            }
        }

        return $values;
    }

    /**
     * Validates and sanitizes a value from the command line against its schema, like WordPress does for REST
     * arguments, and creates the typed value. Values are strings or booleans (flags); WP-CLI fills in the
     * defaults of the synopsis as parsed from YAML, e.g. integers.
     *
     * @param Value                $value
     * @param array<string, mixed> $schema
     */
    private function convert(array $value, array $schema, mixed $raw, string $name, string $label): mixed
    {
        $valid = rest_validate_value_from_schema($raw, $schema, $name);
        if (is_wp_error($valid)) {
            throw new CliException(sprintf('Invalid value for %s: %s', $label, $valid->get_error_message()));
        }

        return $this->values->create($value, rest_sanitize_value_from_schema($raw, $schema, $name), $label);
    }

    /**
     * The default value of an optional argument or option, as recorded by the build.
     *
     * @param CliArgument $argument
     */
    private function default(array $argument, string $label): mixed
    {
        if (!array_key_exists('default', $argument) || !isset($argument['value'])) {
            // WP-CLI checks required arguments and options against the synopsis before calling the command.
            throw new CliException(sprintf('Missing value for %s.', $label));
        }

        return $this->values->create($argument['value'], $argument['default'], $label);
    }

    /**
     * An empty group: WP-CLI turns an object without public methods into a command that only lists its subcommands.
     */
    private static function group(): object
    {
        return new class {};
    }
}
