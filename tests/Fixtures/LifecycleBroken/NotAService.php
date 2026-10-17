<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\LifecycleBroken;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Lifecycle\OnActivation;

#[NoService]
final class NotAService
{
    #[OnActivation]
    public function run(): void {}
}
