<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsBroken;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('not_readonly', title: 'Not readonly')]
final class NotReadonly
{
    public function __construct(public string $value = '') {}
}
