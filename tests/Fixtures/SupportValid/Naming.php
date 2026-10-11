<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportValid;

final class Naming
{
    public function className(string $support, string $value): string
    {
        return $support . '-' . $value;
    }
}
