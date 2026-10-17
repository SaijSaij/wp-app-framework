<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use WebCodingCat\WpAppFramework\Value\PlainText;

final readonly class ExcerptInnerAttributes
{
    public function __construct(
        #[PlainText(excerpt: true)]
        public string $title = '',
    ) {}
}
