<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\IdentifierValid;

use Saij\WpAppFramework\Value\Identifier;

final readonly class Scheme
{
    public function __construct(
        #[Identifier]
        public string $id = '',
        public string $name = '',
    ) {}
}
