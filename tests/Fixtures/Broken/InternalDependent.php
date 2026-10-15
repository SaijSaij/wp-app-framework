<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Broken;

use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Post\PostStore;

final class InternalDependent
{
    // @phpstan-ignore constructor.unusedParameter, constructor.unusedParameter (the parameters only declare dependencies for the container)
    public function __construct(RenderContext $context, PostStore $posts) {}
}
