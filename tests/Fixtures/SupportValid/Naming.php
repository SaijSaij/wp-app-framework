<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportValid;

final class Naming
{
    public function className(string $support, string $value): string
    {
        return $support . '-' . $value;
    }
}
