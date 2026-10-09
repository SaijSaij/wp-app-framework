<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RestListBroken;

use Saij\WpAppFramework\DependencyInjection\NoService;

#[NoService]
final readonly class Item
{
    public function __construct(public string $title = '')
    {
    }
}
