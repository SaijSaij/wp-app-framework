<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ExternalParent;

use WP_Widget as BaseWidget;

// Builds on WordPress through an alias; not loaded by the build.
#[\AllowDynamicProperties]
final class Widget extends BaseWidget
{
    #[\Override]
    public function widget($args, $instance): void {}
}
