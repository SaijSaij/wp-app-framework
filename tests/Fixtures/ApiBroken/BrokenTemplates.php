<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ApiBroken;

use Saij\WpAppFramework\Twig\TemplateFunction;
use Twig\Markup;

final class BrokenTemplates
{
    #[TemplateFunction('unprefixed', public: true)]
    public function unprefixed(): string
    {
        return '';
    }

    #[TemplateFunction('my_plugin_leak', public: true)]
    public function leak(Markup $markup): string
    {
        return (string) $markup;
    }

    #[TemplateFunction('other_plugin_name')]
    public function reserved(): string
    {
        return '';
    }
}
