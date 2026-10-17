<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CacheValid;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('site', title: 'Site')]
final readonly class SiteSettings
{
    public function __construct(public string $tagline = '') {}
}
