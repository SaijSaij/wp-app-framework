<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ApiBroken;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Twig\TemplateRenderer;

/**
 * Part of the public API through LeakingApi::wrapper(), so its public members are checked too.
 */
#[NoService]
final class Wrapper
{
    public ?TemplateRenderer $renderer = null;
}
