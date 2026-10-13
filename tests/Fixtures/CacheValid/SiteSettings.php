<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CacheValid;

use Saij\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('site', title: 'Site')]
final readonly class SiteSettings
{
    public function __construct(public string $tagline = '') {}
}
