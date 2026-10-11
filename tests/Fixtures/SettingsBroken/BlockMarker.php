<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsBroken;

use Saij\WpAppFramework\Block\RichText;
use Saij\WpAppFramework\Setting\SettingsGroup;

#[SettingsGroup('block_marker', title: 'Block marker')]
final readonly class BlockMarker
{
    public function __construct(
        #[RichText(postContent: true)]
        public string $text = '',
    ) {}
}
