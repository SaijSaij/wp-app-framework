<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsBroken;

use Saij\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('duplicate', title: 'Duplicate B')]
final readonly class DuplicateB
{
    public function __construct(public string $value = '') {}
}
