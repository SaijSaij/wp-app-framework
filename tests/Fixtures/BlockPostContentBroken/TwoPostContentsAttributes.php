<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use Saij\WpAppFramework\Value\RichText;

final readonly class TwoPostContentsAttributes
{
    public function __construct(
        #[RichText(postContent: true)]
        public string $first = '',
        #[RichText(postContent: true)]
        public string $second = '',
    ) {}
}
