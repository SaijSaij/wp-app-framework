<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\IdentifierBroken;

use WebCodingCat\WpAppFramework\Value\Identifier;

final readonly class Item
{
    public function __construct(
        #[Identifier]
        public string $id = '',
    ) {}
}
