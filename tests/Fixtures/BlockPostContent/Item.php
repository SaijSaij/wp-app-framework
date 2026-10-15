<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContent;

use Saij\WpAppFramework\Value\PlainText;

final readonly class Item
{
    public function __construct(
        #[PlainText(excerpt: true)]
        public string $label = '',
        #[PlainText]
        public string $note = '',
    ) {}
}
