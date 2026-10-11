<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportValid;

use Saij\WpAppFramework\Block\Labeled;

enum Tone: string implements Labeled
{
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
