<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Vary;

use Saij\WpAppFramework\Cache\CacheVary;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A header of the request, e.g. Accept-Language; a missing header differs from an empty one.
 *
 * @api Cache
 */
#[NoService]
final readonly class HeaderVary implements CacheVary
{
    /**
     * @param string $name The name of the header, not empty, e.g. `Accept-Language`; case does not matter.
     */
    public function __construct(private string $name) {}

    /**
     * The name as given to the constructor.
     */
    public function getName(): string
    {
        return $this->name;
    }
}
