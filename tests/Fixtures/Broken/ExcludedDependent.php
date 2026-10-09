<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Broken;

final class ExcludedDependent
{
    public function __construct(Excluded $excluded)
    {
    }
}
