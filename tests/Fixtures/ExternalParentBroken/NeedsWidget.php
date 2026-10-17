<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ExternalParentBroken;

use WebCodingCat\WpAppFramework\Hook\Hook;

final class NeedsWidget
{
    public function __construct(private readonly Widget $widget) {}

    #[Hook('init')]
    public function run(): void
    {
        register_widget($this->widget);
    }
}
