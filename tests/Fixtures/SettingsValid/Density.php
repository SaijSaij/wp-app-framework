<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsValid;

use Saij\WpAppFramework\Value\Labeled;

enum Density: string implements Labeled
{
    case Compact = 'compact';
    case Normal = 'normal';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
