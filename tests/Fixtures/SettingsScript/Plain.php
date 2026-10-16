<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsScript;

use Saij\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('plain', title: 'Plain')]
final readonly class Plain
{
    public function __construct(public bool $on = false) {}
}
