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

#[RestController('fixture/v1', base: '/stats')]
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
}
