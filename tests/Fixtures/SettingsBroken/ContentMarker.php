<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsBroken;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;
use WebCodingCat\WpAppFramework\Value\Content;

#[SettingsGroup('content_marker', title: 'Content marker')]
final readonly class ContentMarker
{
    public function __construct(
        #[Content]
        public string $url = '',
    ) {}
}
