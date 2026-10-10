<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContent;

use Saij\WpAppFramework\Block\RichText;

final readonly class ParagraphAttributes
{
    public function __construct(
        #[RichText(allowedFormats: ['core/bold'], postContent: true, excerpt: true)]
        public string $text = '',
        public bool $lead = false,
    ) {}
}
