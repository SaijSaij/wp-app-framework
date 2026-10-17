<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockValid;

final class Formatter
{
    public function format(string $value): string
    {
        return '[' . $value . ']';
    }
}
