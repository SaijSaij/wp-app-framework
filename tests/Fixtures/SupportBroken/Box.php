<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportBroken;

final readonly class Box
{
    public function __construct(public string $name = '') {}
}
