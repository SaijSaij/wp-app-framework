<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsValid;

use Saij\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('layout', title: 'Layout', position: 5)]
final readonly class Layout
{
    public function __construct(public bool $wide = false) {}
}
