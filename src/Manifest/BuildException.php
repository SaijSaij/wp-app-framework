<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest;

use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * The manifest cannot be built; lists every problem found.
 */
#[NoService]
final class BuildException extends RuntimeException
{
    /**
     * @param non-empty-list<string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct("Manifest build failed:\n - " . implode("\n - ", $errors));
    }
}
