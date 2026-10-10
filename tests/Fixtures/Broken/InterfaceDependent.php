<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Broken;

final class InterfaceDependent
{
    // @phpstan-ignore constructor.unusedParameter (the parameter only declares a dependency for the container)
    public function __construct(LoggerLike $logger) {}
}
