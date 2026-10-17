<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsBroken;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('no_default', title: 'No default')]
final readonly class NoDefault
{
    public function __construct(public string $value) {}
}
