<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsBroken;

use WebCodingCat\WpAppFramework\Value\RichText;

final readonly class RichItem
{
    public function __construct(
        #[RichText(allowedFormats: ['core/bold', 'core/code'])]
        public string $text = '',
    ) {}
}
