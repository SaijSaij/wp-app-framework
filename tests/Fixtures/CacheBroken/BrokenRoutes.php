<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CacheBroken;

use WebCodingCat\WpAppFramework\Cache\Cache;
use WebCodingCat\WpAppFramework\Rest\HttpMethod;
use WebCodingCat\WpAppFramework\Rest\Permission\PublicAccess;
use WebCodingCat\WpAppFramework\Rest\RestController;
use WebCodingCat\WpAppFramework\Rest\RestRoute;

#[RestController('v1', base: '/broken')]
final class BrokenRoutes
{
    /**
     * @return list<string>
     */
    #[RestRoute('/write', [HttpMethod::Get, HttpMethod::Post], new PublicAccess())]
    #[Cache(ttl: 60)]
    public function write(): array
    {
        return [];
    }

    #[RestRoute('/none', HttpMethod::Get, new PublicAccess())]
    #[Cache(ttl: 60)]
    public function none(): void {}
}
