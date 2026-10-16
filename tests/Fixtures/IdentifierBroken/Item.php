<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\IdentifierBroken;

use Saij\WpAppFramework\Value\Identifier;

final readonly class Item
{
    public function __construct(
        #[Identifier]
        public string $id = '',
    ) {}
}
