<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsBroken;

use Saij\WpAppFramework\Hook\Hook;

final class UsedInHook
{
    #[Hook('fixture_hook')]
    public function run(Valid $settings): void {}
}
