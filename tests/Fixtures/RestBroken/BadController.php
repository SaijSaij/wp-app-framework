<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RestBroken;

use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\Permission\PublicAccess;
use Saij\WpAppFramework\Rest\RestController;
use Saij\WpAppFramework\Rest\RestRoute;

#[RestController('/broken/', base: 'items/')]
final class BadController
{
    #[RestRoute('/a', HttpMethod::Get, new PublicAccess())]
    public function a(): bool
    {
        return true;
    }
}
