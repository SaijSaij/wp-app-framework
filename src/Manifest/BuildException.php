<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Manifest;

use RuntimeException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * The manifest cannot be built; lists every problem found.
 */
#[NoService]
final class BuildException extends RuntimeException
{
    /**
     * @param non-empty-list<string> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct("Manifest build failed:\n - " . implode("\n - ", $errors));
    }

    /**
     * @return non-empty-list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
