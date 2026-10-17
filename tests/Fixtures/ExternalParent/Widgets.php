<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ExternalParent;

use WebCodingCat\WpAppFramework\Hook\Hook;

// Creates the widget itself, when WordPress is loaded.
final class Widgets
{
    #[Hook('widgets_init')]
    public function register(Storage $storage): void
    {
        register_widget(new Widget('fixture', 'Fixture'));
    }
}
