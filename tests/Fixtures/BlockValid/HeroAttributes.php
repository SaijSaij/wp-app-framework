<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockValid;

use Saij\WpAppFramework\Block\Content;
use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Block\RichText;
use Saij\WpAppFramework\Value\ListOf;

final readonly class HeroAttributes
{
    /**
     * @param list<Button> $buttons
     * @param list<Size>   $sizes
     */
    public function __construct(
        #[PlainText] public string $topline = '',
        #[RichText] public string $headline = '',
        #[Content] public ?int $imageId = null,
        public Gradient $gradient = Gradient::Black,
        #[ListOf(Button::class)] public array $buttons = [],
        #[Content] public Link $link = new Link(),
        public int $count = 3,
        public float $ratio = 0.5,
        public bool $highlight = false,
        #[ListOf(Size::class)] public array $sizes = [Size::Small],
        public ?Link $secondary = null,
    ) {
    }
}
