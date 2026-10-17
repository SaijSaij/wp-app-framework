<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\IdentifierValid;

use WebCodingCat\WpAppFramework\Value\Identifier;

final readonly class Pane
{
    public function __construct(
        #[Identifier]
        public string $key = '',
        public string $note = '',
    ) {}
}
