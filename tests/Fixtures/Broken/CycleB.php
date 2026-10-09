<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Broken;

final class CycleB
{
    public function __construct(CycleA $a)
    {
    }
}
