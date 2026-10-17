<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsValid;

use WebCodingCat\WpAppFramework\Value\Labeled;

enum Density: string implements Labeled
{
    case Compact = 'compact';
    case Normal = 'normal';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
