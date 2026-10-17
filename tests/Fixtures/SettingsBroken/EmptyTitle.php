<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsBroken;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('empty_title', title: ' ')]
final readonly class EmptyTitle
{
    public function __construct(public string $value = '') {}
}
