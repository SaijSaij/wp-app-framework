<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Broken;

final class ExcludedDependent
{
    // @phpstan-ignore constructor.unusedParameter (the parameter only declares a dependency for the container)
    public function __construct(Excluded $excluded) {}
}
