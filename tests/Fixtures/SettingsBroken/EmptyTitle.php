<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsBroken;

use Saij\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('empty_title', title: ' ')]
final readonly class EmptyTitle
{
    public function __construct(public string $value = '') {}
}
