<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsBroken;

use WebCodingCat\WpAppFramework\Hook\Hook;

final class UsedInHook
{
    #[Hook('fixture_hook')]
    public function run(Valid $settings): void {}
}
