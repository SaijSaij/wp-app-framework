<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Broken;

use ArrayObject;

final class OutsideDependent
{
    public function __construct(ArrayObject $storage)
    {
    }
}
