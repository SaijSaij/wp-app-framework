<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use WebCodingCat\WpAppFramework\Value\RichText;

final readonly class PostContentDefaultAttributes
{
    public function __construct(
        #[RichText(postContent: true)]
        public string $text = 'Hello',
    ) {}
}
