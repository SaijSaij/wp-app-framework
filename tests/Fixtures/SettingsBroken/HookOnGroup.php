<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsBroken;

use WebCodingCat\WpAppFramework\Hook\Hook;
use WebCodingCat\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('hook_on_group', title: 'Hook on group')]
final readonly class HookOnGroup
{
    public function __construct(public string $value = '') {}

    #[Hook('fixture_group_hook')]
    public function run(): void {}
}
