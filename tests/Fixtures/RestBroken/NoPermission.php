<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RestBroken;

use WebCodingCat\WpAppFramework\Rest\HttpMethod;
use WebCodingCat\WpAppFramework\Rest\RestController;
use WebCodingCat\WpAppFramework\Rest\RestRoute;

#[RestController('v1', base: '/no-permission')]
final class NoPermission
{
    // The permission argument is missing on purpose.
    // @phpstan-ignore arguments.count (fixture: the missing permission is the error under test)
    #[RestRoute('/a', HttpMethod::Get)]
    public function a(): bool
    {
        return true;
    }
}
