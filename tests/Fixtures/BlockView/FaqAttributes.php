<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockView;

use WebCodingCat\WpAppFramework\Value\PlainText;

final readonly class FaqAttributes
{
    public function __construct(
        #[PlainText(excerpt: true)]
        public string $question = '',
    ) {}
}
