<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ListLimitValid;

use WebCodingCat\WpAppFramework\Value\ListOf;
use WebCodingCat\WpAppFramework\Value\PlainText;

final readonly class Slide
{
    /**
     * @param list<Tone> $tones
     */
    public function __construct(
        #[PlainText]
        public string $caption = '',
        #[ListOf(Tone::class, min: 1, max: 1)]
        public array $tones = [Tone::Dark],
    ) {}
}
