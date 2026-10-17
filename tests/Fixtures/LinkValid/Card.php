<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\LinkValid;

use WebCodingCat\WpAppFramework\Value\Link;
use WebCodingCat\WpAppFramework\Value\PlainText;

final readonly class Card
{
    public function __construct(
        #[PlainText(multiline: true)]
        public string $text = '',
        public Link $link = new Link(),
    ) {}
}
