<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\DateTimeValid;

use WebCodingCat\WpAppFramework\Value\DateTime;

final readonly class Rehearsal
{
    public function __construct(
        #[DateTime]
        public string $at = '2026-10-31T18:30',
    ) {}
}
