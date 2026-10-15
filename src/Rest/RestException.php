<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Rest;

use InvalidArgumentException;
use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Throwable;

/**
 * Ends a REST request with an error response: thrown from a route or permission
 * method, it becomes a WP_Error with the given code and HTTP status.
 *
 * @api Values and exceptions
 */
#[NoService]
final class RestException extends RuntimeException
{
    /**
     * @param string               $errorCode Machine-readable error code, e.g. `sections_item_not_found`.
     * @param string               $message   The error message for the client, readable by humans.
     * @param int                  $status    HTTP status, 400–599.
     * @param array<string, mixed> $data      Additional error data; `status` is always the given status.
     * @param Throwable|null       $previous  The exception that caused this one, if any.
     * @throws InvalidArgumentException If the status is not between 400 and 599.
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status,
        private readonly array $data = [],
        ?Throwable $previous = null,
    ) {
        if ($status < 400 || $status > 599) {
            throw new InvalidArgumentException(sprintf('RestException "%s" needs an HTTP error status between 400 and 599, %d given.', $errorCode, $status));
        }

        parent::__construct($message, 0, $previous);
    }

    /**
     * The machine-readable error code of the response.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * The HTTP status of the response, 400 to 599.
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * The additional error data as given to the constructor; in the response, `status` is always the status of the
     * exception.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
