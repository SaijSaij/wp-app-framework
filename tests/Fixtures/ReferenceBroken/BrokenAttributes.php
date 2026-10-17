<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ReferenceBroken;

use WebCodingCat\WpAppFramework\Value\Content;
use WebCodingCat\WpAppFramework\Value\ListOf;
use WebCodingCat\WpAppFramework\Value\PostReference;
use WebCodingCat\WpAppFramework\Value\TermReference;
use WebCodingCat\WpAppFramework\Value\UserReference;

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
