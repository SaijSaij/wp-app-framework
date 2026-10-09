<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TwigBroken;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Twig\TemplateFunction;

#[NoService]
final class NotAService
{
    #[TemplateFunction('excluded')]
    public function excluded(): string
    {
        return '';
    }
}
