<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use WebCodingCat\WpAppFramework\Value\Labeled;

enum EmptyLabel: string implements Labeled
{
    case A = 'a';

    public function label(): string
    {
        return '';
    }
}
