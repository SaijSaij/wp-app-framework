<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Broken;

final class InterfaceDependent
{
    public function __construct(LoggerLike $logger)
    {
    }
}
