<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ApiBroken;

use Twig\Environment;
use WebCodingCat\WpAppFramework\Api\PublicApi;

#[PublicApi]
final class LeakingApi
{
    public function environment(?Environment $environment): ?Environment
    {
        return $environment;
    }

    public function wrapper(): Wrapper
    {
        return new Wrapper();
    }
}
