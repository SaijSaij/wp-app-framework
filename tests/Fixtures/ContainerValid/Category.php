<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerValid;

use Saij\WpAppFramework\Block\CustomBlockCategory;

enum Category: string implements CustomBlockCategory
{
    case Sections = 'sections';
    case Extras = 'extras';

    public function label(): string
    {
        return match ($this) {
            self::Sections => 'Sections',
            self::Extras => 'Extras',
        };
    }

    public function icon(): ?string
    {
        return $this === self::Sections ? 'layout' : null;
    }
}
