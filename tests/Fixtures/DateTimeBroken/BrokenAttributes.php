<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\DateTimeBroken;

use Saij\WpAppFramework\Value\Color;
use Saij\WpAppFramework\Value\DateTime;
use Saij\WpAppFramework\Value\PlainText;

final readonly class BrokenAttributes
{
    public function __construct(
        #[DateTime]
        public int $timestamp = 0,
        #[DateTime]
        public ?string $nullable = null,
        #[DateTime]
        #[PlainText]
        public string $plain = '',
        #[DateTime]
        #[Color]
        public string $color = '',
        #[DateTime]
        public string $withSeconds = '2026-11-14T20:00:00',
        #[DateTime(time: false)]
        public string $withTime = '2026-11-14T20:00',
        #[DateTime]
        public string $missing = '2026-02-30T20:00',
    ) {}
}
