<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Vary;

use Saij\WpAppFramework\Cache\CacheVary;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A parameter of the query string ($_GET); a missing parameter differs from an empty one.
 */
#[NoService]
final readonly class QueryParamVary implements CacheVary
{
    public function __construct(private string $name) {}

    public function getName(): string
    {
        return $this->name;
    }
}
