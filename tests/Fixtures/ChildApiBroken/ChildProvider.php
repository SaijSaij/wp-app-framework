<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ChildApiBroken;

use WebCodingCat\WpAppFramework\Api\PublicApi;
use WebCodingCat\WpAppFramework\Twig\TemplateFunction;

/**
 * Child themes have no consumers, so they offer nothing public.
 */
#[PublicApi]
final class ChildProvider
{
    #[TemplateFunction('my_child_count', public: true)]
    public function count(): int
    {
        return 1;
    }
}
