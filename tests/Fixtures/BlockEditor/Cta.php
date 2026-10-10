<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditor;

use Saij\WpAppFramework\Block\Image;
use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Value\Url;

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
