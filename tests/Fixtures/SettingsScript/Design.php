<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsScript;

use Saij\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('color_schemes', title: 'Color schemes')]
final readonly class Design
{
    public function __construct(public string $title = '') {}
}
