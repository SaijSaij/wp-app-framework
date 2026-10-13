<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerBroken;

use Saij\WpAppFramework\Block\Context;

final class Service
{
    public function run(#[Context(Box::class, 'tone')] string $tone): string
    {
        return $tone;
    }
}
