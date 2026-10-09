<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Valid;

use Saij\WpAppFramework\DependencyInjection\NoService;

#[NoService]
final readonly class Context
{
    public function __construct(public string $label)
    {
    }
}
