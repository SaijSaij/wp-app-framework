<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsBroken;

use Saij\WpAppFramework\Setting\SettingsGroup;
use Saij\WpAppFramework\Value\ListOf;

#[SettingsGroup('nested_marker', title: 'Nested marker')]
final readonly class NestedMarker
{
    /**
     * @param list<RichItem> $items
     */
    public function __construct(
        #[ListOf(RichItem::class)]
        public array $items = [],
    ) {}
}
