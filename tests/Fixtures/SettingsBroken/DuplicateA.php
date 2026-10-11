<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsBroken;

use Saij\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('duplicate', title: 'Duplicate A')]
final readonly class DuplicateA
{
    public function __construct(public string $value = '') {}
}
