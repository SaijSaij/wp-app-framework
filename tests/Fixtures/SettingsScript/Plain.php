<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsScript;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('plain', title: 'Plain')]
final readonly class Plain
{
    public function __construct(public bool $on = false) {}
}
