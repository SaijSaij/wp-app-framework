<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RestList;

use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\Param;
use Saij\WpAppFramework\Rest\Permission\Callback;
use Saij\WpAppFramework\Rest\RestController;
use Saij\WpAppFramework\Rest\RestRoute;
use Saij\WpAppFramework\Value\ListOf;

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
        #[ListOf(Entry::class)]
        array $entries,
        #[Param(description: 'Filter')]
        #[ListOf(Priority::class)]
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
