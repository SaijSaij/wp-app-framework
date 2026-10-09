<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Broken;

final class CycleA
{
    public function __construct(CycleB $b)
    {
    }
}
