<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsBroken;

use Saij\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('Bad-Name', title: 'Bad')]
final readonly class BadName
{
    public function __construct(public string $value = '') {}
}
