<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\IdentifierBroken;

use Saij\WpAppFramework\Value\Hidden;
use Saij\WpAppFramework\Value\Identifier;
use Saij\WpAppFramework\Value\PlainText;

final readonly class BrokenItem
{
    public function __construct(
        #[Identifier]
        public int $number = 0,
        #[Identifier]
        public ?string $nullable = null,
        #[Identifier]
        #[PlainText]
        public string $plain = '',
        #[Identifier]
        #[Hidden]
        public string $hidden = '',
        #[Identifier]
        public string $preset = '0a1b2c3d',
    ) {}
}
