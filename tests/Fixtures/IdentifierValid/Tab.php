<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\IdentifierValid;

use WebCodingCat\WpAppFramework\Value\Identifier;
use WebCodingCat\WpAppFramework\Value\ListOf;
use WebCodingCat\WpAppFramework\Value\PlainText;

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
