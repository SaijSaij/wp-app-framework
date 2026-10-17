<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsValid;

use WebCodingCat\WpAppFramework\Value\Image;
use WebCodingCat\WpAppFramework\Value\Url;

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
