<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest\Discovery;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A PSR-4 namespace prefix and the directory it maps to.
 */
#[NoService]
final readonly class Source
{
    /**
     * @param string $prefix    Namespace prefix with trailing backslash, e.g. `Saij\ThemeSections\`.
     * @param string $directory Absolute real path without trailing slash.
     */
    public function __construct(
        private string $prefix,
        private string $directory,
    ) {
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }
}
