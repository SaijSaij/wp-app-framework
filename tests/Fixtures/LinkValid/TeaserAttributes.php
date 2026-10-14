<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\LinkValid;

use Saij\WpAppFramework\Block\Label;
use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Value\Link;
use Saij\WpAppFramework\Value\ListOf;

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
