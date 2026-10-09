<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RestBroken;

use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\RestController;
use Saij\WpAppFramework\Rest\RestRoute;

#[RestController('broken/v1', base: '/no-permission')]
final class NoPermission
{
    // The permission argument is missing on purpose.
    #[RestRoute('/a', HttpMethod::Get)]
    public function a(): bool
    {
        return true;
    }
}
