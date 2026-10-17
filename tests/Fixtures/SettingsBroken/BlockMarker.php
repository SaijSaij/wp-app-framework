<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsBroken;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;
use WebCodingCat\WpAppFramework\Value\RichText;

#[SettingsGroup('block_marker', title: 'Block marker')]
final readonly class BlockMarker
{
    public function __construct(
        #[RichText(postContent: true)]
        public string $text = '',
    ) {}
}
