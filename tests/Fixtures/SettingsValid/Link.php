<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsValid;

use Saij\WpAppFramework\Value\Image;
use Saij\WpAppFramework\Value\Url;

final readonly class Link
{
    public function __construct(
        public string $label,
        #[Url]
        public string $url = '',
        #[Image]
        public ?int $icon = null,
    ) {}
}
