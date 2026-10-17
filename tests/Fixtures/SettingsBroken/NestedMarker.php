<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsBroken;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;
use WebCodingCat\WpAppFramework\Value\ListOf;

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
