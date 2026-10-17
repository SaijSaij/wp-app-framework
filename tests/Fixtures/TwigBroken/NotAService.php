<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TwigBroken;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Twig\TemplateFunction;

#[NoService]
final class NotAService
{
    #[TemplateFunction('excluded')]
    public function excluded(): string
    {
        return '';
    }
}
