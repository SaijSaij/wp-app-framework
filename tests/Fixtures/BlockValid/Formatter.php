<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockValid;

final class Formatter
{
    public function format(string $value): string
    {
        return '[' . $value . ']';
    }
}
