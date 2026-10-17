<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\IdentifierValid;

use WebCodingCat\WpAppFramework\Value\ListOf;

final readonly class TabsAttributes
{
    /**
     * @param list<Tab> $tabs
     */
    public function __construct(
        #[ListOf(Tab::class, appender: true)]
        public array $tabs = [],
    ) {}
}
