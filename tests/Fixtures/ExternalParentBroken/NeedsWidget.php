<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ExternalParentBroken;

use Saij\WpAppFramework\Hook\Hook;

final class NeedsWidget
{
    public function __construct(private readonly Widget $widget) {}

    #[Hook('init')]
    public function run(): void
    {
        register_widget($this->widget);
    }
}
