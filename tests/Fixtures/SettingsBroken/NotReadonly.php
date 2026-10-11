<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsBroken;

use Saij\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('not_readonly', title: 'Not readonly')]
final class NotReadonly
{
    public function __construct(public string $value = '') {}
}
