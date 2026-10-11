<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsBroken;

use Saij\WpAppFramework\Block\RichText;

final readonly class RichItem
{
    public function __construct(
        #[RichText(allowedFormats: ['core/bold', 'core/code'])]
        public string $text = '',
    ) {}
}
