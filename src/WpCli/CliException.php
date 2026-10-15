<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\WpCli;

use InvalidArgumentException;
use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Throwable;

/**
 * Ends a WP-CLI command with an error: thrown from a `#[CliSubcommand]` method,
 * WP-CLI prints `Error: {message}` and exits with the given code.
 *
 * @api Values and exceptions
 */
#[NoService]
final class CliException extends RuntimeException
{
    /**
     * @param string         $message  The error message WP-CLI prints after `Error: `.
     * @param int            $exitCode Exit code of the command, 1–255.
     * @param Throwable|null $previous The exception that caused this one, if any.
     * @throws InvalidArgumentException If the exit code is not between 1 and 255.
     */
    public function __construct(string $message, private readonly int $exitCode = 1, ?Throwable $previous = null)
    {
        if ($exitCode < 1 || $exitCode > 255) {
            throw new InvalidArgumentException(sprintf('CliException "%s" needs an exit code between 1 and 255, %d given.', $message, $exitCode));
        }

        parent::__construct($message, 0, $previous);
    }

    /**
     * The exit code of the command, 1 to 255.
     */
    public function getExitCode(): int
    {
        return $this->exitCode;
    }
}
