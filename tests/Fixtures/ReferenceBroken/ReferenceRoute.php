<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ReferenceBroken;

use WebCodingCat\WpAppFramework\Rest\HttpMethod;
use WebCodingCat\WpAppFramework\Rest\Permission\PublicAccess;
use WebCodingCat\WpAppFramework\Rest\RestController;
use WebCodingCat\WpAppFramework\Rest\RestRoute;
use WebCodingCat\WpAppFramework\Value\PostReference;

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
