<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use Saij\WpAppFramework\Value\PlainText;

final readonly class ExcerptInnerAttributes
{
    public function __construct(
        #[PlainText(excerpt: true)]
        public string $title = '',
    ) {}
}
