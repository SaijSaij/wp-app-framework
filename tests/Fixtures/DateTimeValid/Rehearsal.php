<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\DateTimeValid;

use Saij\WpAppFramework\Value\DateTime;

final readonly class Rehearsal
{
    public function __construct(
        #[DateTime]
        public string $at = '2026-10-31T18:30',
    ) {}
}
