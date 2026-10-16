<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditor;

use Saij\WpAppFramework\Value\Content;
use Saij\WpAppFramework\Value\Hidden;
use Saij\WpAppFramework\Value\Image;
use Saij\WpAppFramework\Value\Label;
use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\PlainText;
use Saij\WpAppFramework\Value\RichText;
use Saij\WpAppFramework\Value\Url;

final readonly class TeaserAttributes
{
    /**
     * @param list<Cta> $ctas
     */
    public function __construct(
        #[PlainText]
        #[Label('Kicker line')]
        public string $kicker = '',
        #[RichText(allowedFormats: ['core/bold'])]
        public string $title = '',
        #[Image]
        public ?int $imageId = null,
        public Tone $tone = Tone::Light,
        public ?Size $size = null,
        #[ListOf(Cta::class, itemLabel: 'Call to action', appender: true)]
        public array $ctas = [],
        #[Hidden]
        public int $version = 1,
        public bool $featured = false,
        public float $ratio = 1.5,
        #[Url]
        public string $link = '',
        public Caption $caption = new Caption(),
        #[Content]
        #[Label('Source')]
        public string $source = '',
        #[RichText(inspector: true)]
        public string $note = '',
    ) {}
}
