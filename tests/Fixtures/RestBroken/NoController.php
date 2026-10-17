<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RestBroken;

use WebCodingCat\WpAppFramework\Rest\HttpMethod;
use WebCodingCat\WpAppFramework\Rest\Permission\PublicAccess;
use WebCodingCat\WpAppFramework\Rest\RestRoute;

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
