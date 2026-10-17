<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Broken;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Hook\Hook;

#[NoService]
final class ExcludedWithHook
{
    #[Hook('broken_excluded')]
    public function run(): void {}
}
