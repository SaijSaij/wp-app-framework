<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Broken;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Hook\Hook;

#[NoService]
final class ExcludedWithHook
{
    #[Hook('broken_excluded')]
    public function run(): void {}
}
