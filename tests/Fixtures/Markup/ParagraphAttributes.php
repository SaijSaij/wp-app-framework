<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Markup;

use Saij\WpAppFramework\Block\RichText;

final readonly class ParagraphAttributes
{
    public function __construct(
        #[RichText(postContent: true)]
        public string $text = '',
        public bool $lead = false,
    ) {}
}
