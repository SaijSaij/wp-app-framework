<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\DateTimeValid;

use WebCodingCat\WpAppFramework\Value\DateTime;
use WebCodingCat\WpAppFramework\Value\Label;
use WebCodingCat\WpAppFramework\Value\ListOf;

final readonly class ConcertAttributes
{
    /**
     * @param list<Rehearsal> $rehearsals
     */
    public function __construct(
        #[DateTime]
        public string $start = '',
        #[DateTime(time: false)]
        #[Label('Sale ends')]
        public string $saleEnds = '2026-11-01',
        #[ListOf(Rehearsal::class)]
        public array $rehearsals = [],
    ) {}
}
