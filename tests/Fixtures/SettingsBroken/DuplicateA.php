<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsBroken;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('duplicate', title: 'Duplicate A')]
final readonly class DuplicateA
{
    public function __construct(public string $value = '') {}
}
