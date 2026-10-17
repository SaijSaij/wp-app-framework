<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerBroken;

use WebCodingCat\WpAppFramework\Block\CustomBlockCategory;

enum BadCategory: string implements CustomBlockCategory
{
    case Good = 'good';
    case Empty = 'empty';
    case Blank = 'blank';
    case Upper = 'Upper';

    public function label(): string
    {
        return $this === self::Empty ? '' : 'Label';
    }

    public function icon(): ?string
    {
        return $this === self::Blank ? '' : null;
    }
}
