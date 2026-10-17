<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockEditor;

use WebCodingCat\WpAppFramework\Value\Image;
use WebCodingCat\WpAppFramework\Value\PlainText;
use WebCodingCat\WpAppFramework\Value\Url;

final readonly class Cta
{
    public function __construct(
        #[PlainText]
        public string $label,
        #[Url]
        public string $url = '',
        public ?Size $size = null,
        #[Image(inspector: true)]
        public ?int $iconId = null,
    ) {}
}
