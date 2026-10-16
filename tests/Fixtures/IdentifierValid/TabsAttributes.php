<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\IdentifierValid;

use Saij\WpAppFramework\Value\ListOf;

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
