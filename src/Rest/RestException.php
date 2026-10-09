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
 */
#[NoService]
final class RestException extends RuntimeException
{
    /**
     * @param string               $errorCode Machine-readable error code, e.g. `sections_item_not_found`.
     * @param int                  $status    HTTP status, 400–599.
     * @param array<string, mixed> $data      Additional error data; `status` is always the given status.
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

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
