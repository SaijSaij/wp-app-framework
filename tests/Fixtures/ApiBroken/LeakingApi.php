<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ApiBroken;

use Saij\WpAppFramework\Api\PublicApi;
use Twig\Environment;

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
