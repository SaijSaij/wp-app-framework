<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditor;

use Saij\WpAppFramework\Value\Labeled;

enum Tone: string implements Labeled
{
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Bright',
            self::Dark => 'Night',
        };
    }
}
