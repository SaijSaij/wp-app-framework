<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ListLimitBroken;

use Saij\WpAppFramework\Post\Post;
use Saij\WpAppFramework\Value\Hidden;
use Saij\WpAppFramework\Value\ListOf;

final readonly class BrokenAttributes
{
    /**
     * @param list<Tone> $negative
     * @param list<Tone> $zero
     * @param list<Tone> $crossed
     * @param list<Tone> $tooMany
     * @param list<Tone> $tooFew
     * @param list<Tone> $notExactly
     * @param list<Tone> $emptyLabel
     * @param list<Tone> $hiddenAppender
     * @param list<Post> $postAppender
     */
    public function __construct(
        #[ListOf(Tone::class, min: -1)]
        public array $negative = [],
        #[ListOf(Tone::class, max: 0)]
        public array $zero = [],
        #[ListOf(Tone::class, min: 3, max: 2)]
        public array $crossed = [],
        #[ListOf(Tone::class, max: 1)]
        public array $tooMany = [Tone::Light, Tone::Dark],
        #[ListOf(Tone::class, min: 1)]
        public array $tooFew = [],
        #[ListOf(Tone::class, min: 2, max: 2)]
        public array $notExactly = [Tone::Light],
        #[ListOf(Tone::class, itemLabel: ' ')]
        public array $emptyLabel = [],
        #[Hidden]
        #[ListOf(Tone::class, appender: true)]
        public array $hiddenAppender = [],
        #[ListOf(Post::class, appender: true)]
        public array $postAppender = [],
    ) {}
}
