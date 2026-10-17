<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\WpCli;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WP_CLI;

use function WP_CLI\Utils\format_items;

/**
 * Output of a WP-CLI command: passed to a parameter of this type of a
 * `#[CliSubcommand]` method.
 *
 * Messages go through WP-CLI: `log()` and `success()` write to stdout,
 * `warning()` to stderr; `--quiet` suppresses them. Errors are thrown as
 * CliException.
 *
 * @api Values and exceptions
 */
#[NoService]
final class CliOutput
{
    /**
     * Prints the message as it is.
     *
     * @param string $message The text of the line.
     */
    public function log(string $message): void
    {
        WP_CLI::log($message);
    }

    /**
     * Prints `Success: ` followed by the message.
     *
     * @param string $message The text after `Success: `.
     */
    public function success(string $message): void
    {
        WP_CLI::success($message);
    }

    /**
     * Prints `Warning: ` followed by the message.
     *
     * @param string $message The text after `Warning: `.
     */
    public function warning(string $message): void
    {
        WP_CLI::warning($message);
    }

    /**
     * Prints the rows as table with the given columns.
     *
     * @param list<array<string, scalar|null>> $rows   The rows, each an array of its values by column name.
     * @param list<string>                     $fields Keys of the rows to show, in this order.
     */
    public function table(array $rows, array $fields): void
    {
        format_items('table', $rows, $fields);
    }
}
