<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\IdentifierValid;

use Saij\WpAppFramework\Value\Identifier;
use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\PlainText;

final readonly class Tab
{
    /**
     * @param list<Pane> $panes
     */
    public function __construct(
        #[Identifier]
        public string $id = '',
        #[PlainText]
        public string $title = '',
        #[ListOf(Pane::class)]
        public array $panes = [],
    ) {}
}
