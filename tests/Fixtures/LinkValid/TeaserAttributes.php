<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\LinkValid;

use WebCodingCat\WpAppFramework\Value\Label;
use WebCodingCat\WpAppFramework\Value\Link;
use WebCodingCat\WpAppFramework\Value\ListOf;
use WebCodingCat\WpAppFramework\Value\PlainText;

final readonly class TeaserAttributes
{
    /**
     * @param list<Link> $links
     */
    public function __construct(
        #[Label('Call to action')]
        public Link $cta = new Link(),
        #[ListOf(Link::class)]
        public array $links = [],
        public Card $card = new Card(),
        #[PlainText(multiline: true)]
        public string $address = '',
        #[PlainText(inspector: true, multiline: true)]
        public string $note = '',
    ) {}
}
