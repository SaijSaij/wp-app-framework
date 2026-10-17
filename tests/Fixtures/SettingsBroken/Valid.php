<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsBroken;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('valid', title: 'Valid')]
final readonly class Valid
{
    public function __construct(public string $value = '') {}
}
