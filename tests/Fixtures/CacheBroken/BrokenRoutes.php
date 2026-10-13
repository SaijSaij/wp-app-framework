<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CacheBroken;

use Saij\WpAppFramework\Cache\Cache;
use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\Permission\PublicAccess;
use Saij\WpAppFramework\Rest\RestController;
use Saij\WpAppFramework\Rest\RestRoute;
use WP_REST_Request;
use WP_REST_Response;

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

    #[RestRoute('/response', HttpMethod::Get, new PublicAccess())]
    #[Cache(ttl: 60)]
    public function response(): WP_REST_Response
    {
        return new WP_REST_Response([]);
    }

    #[RestRoute('/none', HttpMethod::Get, new PublicAccess())]
    #[Cache(ttl: 60)]
    public function none(): void {}

    #[RestRoute('/request', HttpMethod::Get, new PublicAccess())]
    #[Cache(ttl: 60)]
    public function request(WP_REST_Request $request): string
    {
        return $request->get_route();
    }
}
