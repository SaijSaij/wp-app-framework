<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RestBroken;

use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\Permission\PublicAccess;
use Saij\WpAppFramework\Rest\RestRoute;

final class NoController
{
    #[RestRoute('/a', HttpMethod::Get, new PublicAccess())]
    public function a(): bool
    {
        return true;
    }

    #[RestRoute('/b', HttpMethod::Get, new PublicAccess())]
    public function b(): bool
    {
        return true;
    }
}
