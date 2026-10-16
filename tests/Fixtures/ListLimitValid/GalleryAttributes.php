<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ListLimitValid;

use Saij\WpAppFramework\Value\ListOf;

final readonly class GalleryAttributes
{
    /**
     * @param list<Slide>    $slides
     * @param list<Tone>     $tones
     * @param list<Tone>     $free
     * @param list<Tone>|null $optional
     */
    public function __construct(
        #[ListOf(Slide::class, max: 3)]
        public array $slides = [],
        #[ListOf(Tone::class, min: 1, max: 2)]
        public array $tones = [Tone::Light],
        #[ListOf(Tone::class)]
        public array $free = [],
        #[ListOf(Tone::class, min: 2)]
        public ?array $optional = null,
    ) {}
}
