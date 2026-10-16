<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ListLimitBroken;

use Saij\WpAppFramework\Setting\SettingsGroup;
use Saij\WpAppFramework\Value\ListOf;

#[SettingsGroup('broken', title: 'Broken')]
final readonly class BrokenSettings
{
    /**
     * @param list<Tone> $tones
     */
    public function __construct(
        #[ListOf(Tone::class, appender: true)]
        public array $tones = [],
    ) {}
}
