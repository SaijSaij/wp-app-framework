<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsBroken;

use Saij\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('valid', title: 'Valid')]
final readonly class Valid
{
    public function __construct(public string $value = '') {}
}
