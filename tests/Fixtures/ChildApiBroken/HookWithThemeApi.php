<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ChildApiBroken;

use Saij\WpAppFramework\Api\ThemeApi;
use Saij\WpAppFramework\Hook\Hook;
use Saij\WpAppFramework\Tests\Fixtures\ParentTheme\SectionsApi;

final class HookWithThemeApi
{
    #[Hook('init')]
    public function run(
        #[ThemeApi]
        ?SectionsApi $sections = null,
    ): void {}
}
