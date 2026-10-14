<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\LifecycleBroken;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Lifecycle\OnActivation;

#[NoService]
final class NotAService
{
    #[OnActivation]
    public function run(): void {}
}
