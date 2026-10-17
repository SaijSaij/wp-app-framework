<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerBroken;

use WebCodingCat\WpAppFramework\Block\Context;

final class Service
{
    public function run(#[Context(Box::class, 'tone')] string $tone): string
    {
        return $tone;
    }
}
