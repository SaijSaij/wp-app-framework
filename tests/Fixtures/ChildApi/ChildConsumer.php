<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ChildApi;

use Saij\WpAppFramework\Api\ThemeApi;
use Saij\WpAppFramework\ChildTheme;
use Saij\WpAppFramework\Hook\Hook;
use Saij\WpAppFramework\Tests\Fixtures\ParentTheme\SectionsApi;

/**
 * A service of a child theme that uses the public API of its parent theme.
 */
final class ChildConsumer
{
    public function __construct(
        private readonly ChildTheme $childTheme,
        #[ThemeApi]
        private readonly ?SectionsApi $sections = null,
    ) {}

    #[Hook('init')]
    public function run(): void {}

    public function hasSections(): bool
    {
        return $this->sections !== null && $this->childTheme->getSlug() !== '';
    }
}
