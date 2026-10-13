<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Vary;

use Saij\WpAppFramework\Cache\CacheVary;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A header of the request, e.g. Accept-Language; a missing header differs from an empty one.
 */
#[NoService]
final readonly class HeaderVary implements CacheVary
{
    public function __construct(private string $name) {}

    public function getName(): string
    {
        return $this->name;
    }
}
