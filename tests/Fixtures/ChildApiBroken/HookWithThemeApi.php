<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ChildApiBroken;

use WebCodingCat\WpAppFramework\Api\ThemeApi;
use WebCodingCat\WpAppFramework\Hook\Hook;
use WebCodingCat\WpAppFramework\Tests\Fixtures\ParentTheme\SectionsApi;

final class HookWithThemeApi
{
    #[Hook('init')]
    public function run(
        #[ThemeApi]
        ?SectionsApi $sections = null,
    ): void {}
}
