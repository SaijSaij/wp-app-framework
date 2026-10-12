<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockView;

use Saij\WpAppFramework\Block\PlainText;

final readonly class FaqAttributes
{
    public function __construct(
        #[PlainText(excerpt: true)]
        public string $question = '',
    ) {}
}
