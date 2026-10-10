<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use Saij\WpAppFramework\Block\RichText;

final readonly class PostContentDefaultAttributes
{
    public function __construct(
        #[RichText(postContent: true)]
        public string $text = 'Hello',
    ) {}
}
