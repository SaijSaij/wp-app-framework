<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsBroken;

use Saij\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('no_default', title: 'No default')]
final readonly class NoDefault
{
    public function __construct(public string $value) {}
}
