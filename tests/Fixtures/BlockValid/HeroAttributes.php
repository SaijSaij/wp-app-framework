<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockValid;

use WebCodingCat\WpAppFramework\Value\Content;
use WebCodingCat\WpAppFramework\Value\ListOf;
use WebCodingCat\WpAppFramework\Value\PlainText;
use WebCodingCat\WpAppFramework\Value\RichText;

final readonly class HeroAttributes
{
    /**
     * @param list<Button> $buttons
     * @param list<Size>   $sizes
     */
    public function __construct(
        #[PlainText]
        public string $topline = '',
        #[RichText]
        public string $headline = '',
        #[Content]
        public ?int $imageId = null,
        public Gradient $gradient = Gradient::Black,
        #[ListOf(Button::class)]
        public array $buttons = [],
        #[Content]
        public Link $link = new Link(),
        public int $count = 3,
        public float $ratio = 0.5,
        public bool $highlight = false,
        #[ListOf(Size::class)]
        public array $sizes = [Size::Small],
        public ?Link $secondary = null,
    ) {}
}
