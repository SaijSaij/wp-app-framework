<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Broken;

use WebCodingCat\WpAppFramework\Editor\RenderContext;
use WebCodingCat\WpAppFramework\Post\PostStore;

final class InternalDependent
{
    // @phpstan-ignore constructor.unusedParameter, constructor.unusedParameter (the parameters only declare dependencies for the container)
    public function __construct(RenderContext $context, PostStore $posts) {}
}
