<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Vary;

use Saij\WpAppFramework\Cache\CacheVary;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A parameter of the query string ($_GET); a missing parameter differs from an empty one.
 *
 * @api Cache
 */
#[NoService]
final readonly class QueryParamVary implements CacheVary
{
    /**
     * @param string $name The name of the parameter in the query string, not empty; compared exactly.
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
