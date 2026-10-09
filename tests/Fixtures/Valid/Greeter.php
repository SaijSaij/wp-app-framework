<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Valid;

final class Greeter
{
    public function __construct(
        private readonly Clock $clock,
        private readonly string $suffix = '!',
    ) {
    }

    public function greet(string $name): string
    {
        return sprintf('Hello %s at %s%s', $name, $this->clock->now(), $this->suffix);
    }
}
