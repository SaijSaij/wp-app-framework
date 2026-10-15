<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\LinkValid;

use Saij\WpAppFramework\Value\Link;
use Saij\WpAppFramework\Value\PlainText;

final readonly class Card
{
    public function __construct(
        #[PlainText(multiline: true)]
        public string $text = '',
        public Link $link = new Link(),
    ) {}
}
