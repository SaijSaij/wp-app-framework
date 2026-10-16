<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\IdentifierValid;

use Saij\WpAppFramework\Value\Identifier;

final readonly class Pane
{
    public function __construct(
        #[Identifier]
        public string $key = '',
        public string $note = '',
    ) {}
}
