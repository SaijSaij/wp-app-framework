<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Markup;

use WebCodingCat\WpAppFramework\Value\RichText;

final readonly class ParagraphAttributes
{
    public function __construct(
        #[RichText(postContent: true)]
        public string $text = '',
        public bool $lead = false,
    ) {}
}
