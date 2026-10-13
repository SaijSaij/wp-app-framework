<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ReferenceBroken;

use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\Permission\PublicAccess;
use Saij\WpAppFramework\Rest\RestController;
use Saij\WpAppFramework\Rest\RestRoute;
use Saij\WpAppFramework\Value\PostReference;

#[RestController('v1')]
final class ReferenceRoute
{
    #[RestRoute('/post', HttpMethod::Get, new PublicAccess())]
    public function post(
        #[PostReference]
        ?int $id = null,
    ): string {
        return (string) $id;
    }
}
