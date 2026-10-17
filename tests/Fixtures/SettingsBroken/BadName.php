<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsBroken;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('Bad-Name', title: 'Bad')]
final readonly class BadName
{
    public function __construct(public string $value = '') {}
}
