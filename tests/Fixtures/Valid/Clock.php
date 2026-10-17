<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Valid;

final class Clock
{
    public function now(): string
    {
        return 'noon';
    }
}
