<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsBroken;

use Saij\WpAppFramework\Setting\SettingsGroup;
use Saij\WpAppFramework\Value\Content;

#[SettingsGroup('content_marker', title: 'Content marker')]
final readonly class ContentMarker
{
    public function __construct(
        #[Content]
        public string $url = '',
    ) {}
}
