<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ChildApi;

use WebCodingCat\WpAppFramework\Api\ThemeApi;
use WebCodingCat\WpAppFramework\ChildTheme;
use WebCodingCat\WpAppFramework\Hook\Hook;
use WebCodingCat\WpAppFramework\Tests\Fixtures\ParentTheme\SectionsApi;

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
