<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use Saij\WpAppFramework\Value\Labeled;

enum EmptyLabel: string implements Labeled
{
    case A = 'a';

    public function label(): string
    {
        return '';
    }
}
