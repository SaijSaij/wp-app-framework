<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RestList;

use WebCodingCat\WpAppFramework\Rest\HttpMethod;
use WebCodingCat\WpAppFramework\Rest\Param;
use WebCodingCat\WpAppFramework\Rest\Permission\Callback;
use WebCodingCat\WpAppFramework\Rest\RestController;
use WebCodingCat\WpAppFramework\Rest\RestRoute;
use WebCodingCat\WpAppFramework\Value\ListOf;

#[RestController('v1')]
final class ListController
{
    /**
     * @param list<Priority> $priorities
     * @param list<Entry>    $entries
     * @return array<string, mixed>
     */
    #[RestRoute('/entries', HttpMethod::Post, new Callback(self::canPost(...)))]
    public function entries(
        #[ListOf(Entry::class, min: 1)]
        array $entries,
        #[Param(description: 'Filter')]
        #[ListOf(Priority::class, max: 2)]
        array $priorities = [Priority::High],
    ): array {
        return ['entries' => $entries, 'priorities' => $priorities];
    }

    /**
     * @param list<Priority> $priorities
     */
    public static function canPost(#[ListOf(Priority::class)] array $priorities = []): bool
    {
        return $priorities !== [Priority::Low];
    }
}
