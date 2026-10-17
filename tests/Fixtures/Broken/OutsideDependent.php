<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Broken;

use ArrayObject;

final class OutsideDependent
{
    /**
     * @param ArrayObject<int, mixed> $storage
     */
    // @phpstan-ignore constructor.unusedParameter (the parameter only declares a dependency for the container)
    public function __construct(ArrayObject $storage) {}
}
