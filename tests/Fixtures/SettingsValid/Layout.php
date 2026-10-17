<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsValid;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('layout', title: 'Layout', position: 5)]
final readonly class Layout
{
    public function __construct(public bool $wide = false) {}
}
