<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ListLimitBroken;

use WebCodingCat\WpAppFramework\Value\ListOf;

final readonly class Row
{
    /**
     * @param list<Tone> $tones
     */
    public function __construct(
        #[ListOf(Tone::class, appender: true)]
        public array $tones = [],
    ) {}
}
