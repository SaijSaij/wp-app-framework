<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ChildApiBroken;

use Saij\WpAppFramework\Api\PluginApi;
use Saij\WpAppFramework\Api\ThemeApi;
use Saij\WpAppFramework\Hook\Hook;
use Saij\WpAppFramework\Tests\Fixtures\ParentTheme\SectionsApi;

final class BrokenChildConsumer
{
    public function __construct(
        #[ThemeApi]
        private readonly SectionsApi $required,
        #[ThemeApi]
        #[PluginApi('other-plugin')]
        private readonly ?SectionsApi $both = null,
    ) {}

    #[Hook('init')]
    public function run(): void {}

    public function same(): bool
    {
        return $this->required === $this->both;
    }
}
