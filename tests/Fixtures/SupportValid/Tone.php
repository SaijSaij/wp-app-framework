<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportValid;

use WebCodingCat\WpAppFramework\Value\Labeled;

enum Tone: string implements Labeled
{
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
