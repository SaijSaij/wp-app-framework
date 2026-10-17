<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContent;

use WebCodingCat\WpAppFramework\Value\RichText;

final readonly class ParagraphAttributes
{
    public function __construct(
        #[RichText(allowedFormats: ['core/bold'], postContent: true, excerpt: true)]
        public string $text = '',
        public bool $lead = false,
    ) {}
}
