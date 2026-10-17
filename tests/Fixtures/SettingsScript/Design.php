<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsScript;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('color_schemes', title: 'Color schemes')]
final readonly class Design
{
    public function __construct(public string $title = '') {}
}
