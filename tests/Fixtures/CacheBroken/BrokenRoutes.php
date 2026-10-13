<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CacheBroken;

use Saij\WpAppFramework\Cache\Cache;
use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\Permission\PublicAccess;
use Saij\WpAppFramework\Rest\RestController;
use Saij\WpAppFramework\Rest\RestRoute;

#[RestController('fixture/v1', base: '/broken')]
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
