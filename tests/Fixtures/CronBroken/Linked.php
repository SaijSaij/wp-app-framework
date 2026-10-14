<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CronBroken;

use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\PostReference;

/**
 * References at several depths.
 */
final readonly class Linked
{
    /**
     * @param list<Author> $authors
     */
    public function __construct(
        #[PostReference]
        public ?int $post,
        public Author $author,
        #[ListOf(Author::class)]
        public array $authors = [],
    ) {}
}
