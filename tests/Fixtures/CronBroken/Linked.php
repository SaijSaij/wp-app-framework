<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronBroken;

use WebCodingCat\WpAppFramework\Value\ListOf;
use WebCodingCat\WpAppFramework\Value\PostReference;

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
