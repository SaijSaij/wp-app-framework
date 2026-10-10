<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ApiBroken;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Twig\TemplateRenderer;

/**
 * Part of the public API through LeakingApi::wrapper(), so its public members are checked too.
 */
#[NoService]
final class Wrapper
{
    public ?TemplateRenderer $renderer = null;
}
