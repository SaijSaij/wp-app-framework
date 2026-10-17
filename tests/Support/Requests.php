<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Support;

use WP_REST_Request;

/**
 * REST requests for tests, built like WordPress does: query and URL parameters set separately.
 */
final class Requests
{
    /**
     * @param array<string, mixed> $query Query parameters.
     * @param array<string, mixed> $url   Parameters matched from the route.
     */
    public static function create(array $query = [], array $url = []): WP_REST_Request
    {
        $request = new WP_REST_Request('GET', '/test');
        $request->set_query_params($query);
        $request->set_url_params($url);

        return $request;
    }
}
