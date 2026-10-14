<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\LinkValid;

use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Value\Link;

final readonly class Card
{
    public function __construct(
        #[PlainText(multiline: true)]
        public string $text = '',
        public Link $link = new Link(),
    ) {}
}
