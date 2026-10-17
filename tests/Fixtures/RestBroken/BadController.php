<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RestBroken;

use WebCodingCat\WpAppFramework\Rest\HttpMethod;
use WebCodingCat\WpAppFramework\Rest\Permission\PublicAccess;
use WebCodingCat\WpAppFramework\Rest\RestController;
use WebCodingCat\WpAppFramework\Rest\RestRoute;

#[RestController('/broken/', base: 'items/')]
final class BadController
{
    #[RestRoute('/a', HttpMethod::Get, new PublicAccess())]
    public function a(): bool
    {
        return true;
    }
}
