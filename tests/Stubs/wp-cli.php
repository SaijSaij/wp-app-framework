<?php

declare(strict_types=1);

/*
 * Minimal test double of the WP-CLI classes and functions used by the
 * framework, with the signatures of WP-CLI 2.12.0 (types from its PHPDoc).
 * PHPStan reads it as signature file (scanFiles in phpstan.dist.neon), because
 * php-stubs/wp-cli-stubs cannot be installed with php-stubs/wordpress-stubs 7.
 *
 * It records registered commands in $GLOBALS['wp_app_framework_test_cli_commands']
 * and messages in $GLOBALS['wp_app_framework_test_cli_output'] (Tests\Support\Recorded);
 * root commands that exist before the framework registers its own are listed
 * in $GLOBALS['wp_app_framework_test_cli_existing']. Like WP-CLI inside
 * WP_CLI::runcommand(), error() and halt() throw ExitException with the exit code.
 */

namespace WP_CLI {
    class ExitException extends \Exception {}
}

namespace WP_CLI\Dispatcher {
    class RootCommand
    {
        /**
         * Removes the first name from $args, like WP-CLI.
         *
         * @param array<int, string> $args
         */
        public function find_subcommand(array &$args): object|false
        {
            $name = (string) array_shift($args);
            if (in_array($name, $GLOBALS['wp_app_framework_test_cli_existing'] ?? [], true) || isset($GLOBALS['wp_app_framework_test_cli_commands'][$name])) {
                return new \stdClass();
            }

            return false;
        }
    }
}

namespace WP_CLI\Utils {
    /**
     * @param array<mixed>         $items
     * @param array<string>|string $fields
     */
    function format_items(string $format, array $items, array|string $fields): void
    {
        $GLOBALS['wp_app_framework_test_cli_output'][] = ['format', $format, $items, $fields];
    }
}

namespace {
    use WP_CLI\Dispatcher\RootCommand;
    use WP_CLI\ExitException;

    class WP_CLI
    {
        public static function get_root_command(): RootCommand
        {
            return new RootCommand();
        }

        /**
         * @param array<string, mixed> $args
         */
        public static function add_command(string $name, callable|object|string $callable, array $args = []): bool
        {
            $GLOBALS['wp_app_framework_test_cli_commands'][$name] = ['callable' => $callable, 'args' => $args];

            return true;
        }

        public static function log(string $message): void
        {
            $GLOBALS['wp_app_framework_test_cli_output'][] = ['log', $message];
        }

        public static function success(string $message): void
        {
            $GLOBALS['wp_app_framework_test_cli_output'][] = ['success', $message];
        }

        public static function warning(string|WP_Error|Exception|Throwable $message): void
        {
            $GLOBALS['wp_app_framework_test_cli_output'][] = ['warning', $message];
        }

        public static function error(string|WP_Error|Exception|Throwable $message, bool|int $exit = true): null
        {
            $GLOBALS['wp_app_framework_test_cli_output'][] = ['error', $message];
            $code = $exit === true ? 1 : (is_int($exit) && $exit >= 1 ? $exit : 0);
            if ($code !== 0) {
                throw new ExitException('', $code);
            }

            return null;
        }

        public static function halt(int $return_code): never
        {
            throw new ExitException('', $return_code);
        }
    }
}
