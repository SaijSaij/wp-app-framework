<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\WpCli;

use Saij\WpAppFramework\DependencyInjection\NoService;
use WP_CLI;

use function WP_CLI\Utils\format_items;

/**
 * Output of a WP-CLI command: passed to a parameter of this type of a
 * `#[CliSubcommand]` method.
 *
 * Messages go through WP-CLI: `log()` and `success()` write to stdout,
 * `warning()` to stderr; `--quiet` suppresses them. Errors are thrown as
 * CliException.
 */
#[NoService]
final class CliOutput
{
    public function log(string $message): void
    {
        WP_CLI::log($message);
    }

    /**
     * Prints "Success: {message}".
     */
    public function success(string $message): void
    {
        WP_CLI::success($message);
    }

    /**
     * Prints "Warning: {message}".
     */
    public function warning(string $message): void
    {
        WP_CLI::warning($message);
    }

    /**
     * Prints the rows as table with the given columns.
     *
     * @param list<array<string, scalar|null>> $rows
     * @param list<string>                     $fields Keys of the rows to show, in this order.
     */
    public function table(array $rows, array $fields): void
    {
        format_items('table', $rows, $fields);
    }
}
