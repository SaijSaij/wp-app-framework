<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ReferenceValid;

use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\PostReference;
use Saij\WpAppFramework\Value\TermReference;
use Saij\WpAppFramework\Value\UserReference;

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
