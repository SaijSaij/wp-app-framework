<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use Saij\WpAppFramework\Block\RichText;

final readonly class PostContentInnerAttributes
{
    public function __construct(
        #[RichText(postContent: true)]
        public string $text = '',
    ) {}
}
