<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ReferenceValid;

use WebCodingCat\WpAppFramework\Value\ListOf;
use WebCodingCat\WpAppFramework\Value\PostReference;
use WebCodingCat\WpAppFramework\Value\TermReference;
use WebCodingCat\WpAppFramework\Value\UserReference;

final readonly class TeaserAttributes
{
    /**
     * @param list<int>  $posts
     * @param list<Card> $cards
     */
    public function __construct(
        #[PostReference(postTypes: ['page'])]
        public ?int $page = null,
        #[PostReference]
        public array $posts = [],
        #[TermReference(taxonomies: ['category'])]
        public ?int $topic = null,
        #[UserReference]
        public ?int $author = null,
        #[ListOf(Card::class)]
        public array $cards = [],
    ) {}
}
