<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsValid;

use WebCodingCat\WpAppFramework\Hook\Hook;

/**
 * A service that receives settings groups through its constructor.
 */
final readonly class Banner
{
    public function __construct(
        private General $general,
        private Layout $layout,
    ) {}

    #[Hook('fixture_banner')]
    public function text(string $value): string
    {
        return sprintf('%s %s %s', $value, $this->general->tagline, $this->layout->wide ? 'wide' : 'narrow');
    }
}
