<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CacheValid;

use Saij\WpAppFramework\Cache\Cache;
use Saij\WpAppFramework\Cache\Vary\UserVary;
use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\Permission\PublicAccess;
use Saij\WpAppFramework\Rest\RestController;
use Saij\WpAppFramework\Rest\RestException;
use Saij\WpAppFramework\Rest\RestRoute;
use WP_REST_Request;
use WP_REST_Response;

#[RestController('v1', base: '/stats')]
final class Stats
{
    /**
     * @return array{limit: int, count: int}
     */
    #[RestRoute('', HttpMethod::Get, new PublicAccess())]
    #[Cache(ttl: 30, tags: [Feed::Weather], vary: [new UserVary()])]
    public function index(int $limit = 5): array
    {
        if ($limit === 0) {
            throw new RestException('fixture_empty', 'No stats.', 404);
        }
        return ['limit' => $limit, 'count' => Renders::count('stats')];
    }

    /**
     * A response with status and header; 404 for the ID 0. The request is no part of the cache key, only $id is.
     */
    #[RestRoute('/detail', HttpMethod::Get, new PublicAccess())]
    #[Cache(ttl: 30)]
    public function detail(WP_REST_Request $request, int $id = 1): WP_REST_Response
    {
        $count = Renders::count('detail');

        return new WP_REST_Response(['id' => $id, 'count' => $count, 'method' => $request->get_method()], $id === 0 ? 404 : 203, ['X-Count' => (string) $count]);
    }
}
