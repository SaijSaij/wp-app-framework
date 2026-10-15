<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ExternalParent;

use Saij\WpAppFramework\Hook\Hook;

// Creates the widget itself, when WordPress is loaded.
final class Widgets
{
    #[Hook('widgets_init')]
    public function register(Storage $storage): void
    {
        register_widget(new Widget('fixture', 'Fixture'));
    }
}
