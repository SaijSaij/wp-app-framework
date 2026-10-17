<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\IdentifierValid;

use WebCodingCat\WpAppFramework\Value\Identifier;

final readonly class Scheme
{
    public function __construct(
        #[Identifier]
        public string $id = '',
        public string $name = '',
    ) {}
}
