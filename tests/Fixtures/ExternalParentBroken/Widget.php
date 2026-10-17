<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ExternalParentBroken;

use WebCodingCat\WpAppFramework\Hook\Hook;

// Framework attributes on a class the build does not load are an error; attributes of PHP are not.
final class Widget extends \WP_Widget
{
    #[Hook('widgets_init')]
    #[\Override]
    public function widget($args, $instance): void {}
}
