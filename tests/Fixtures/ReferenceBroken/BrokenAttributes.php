<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ReferenceBroken;

use Saij\WpAppFramework\Value\Content;
use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\PostReference;
use Saij\WpAppFramework\Value\TermReference;
use Saij\WpAppFramework\Value\UserReference;

final readonly class BrokenAttributes
{
    /**
     * @param list<Item> $withListOf
     */
    public function __construct(
        #[PostReference]
        public int $notNullable = 0,
        #[PostReference]
        public ?string $wrongType = null,
        #[PostReference]
        #[ListOf(Item::class)]
        public array $withListOf = [],
        #[PostReference]
        #[Content]
        public ?int $withContent = null,
        #[PostReference(postTypes: [])]
        public ?int $emptyTypes = null,
        #[TermReference(taxonomies: ['Bad Tax'])]
        public ?int $badTaxonomy = null,
        #[PostReference]
        #[UserReference]
        public ?int $two = null,
    ) {}
}
