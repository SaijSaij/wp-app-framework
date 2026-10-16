<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RestList;

use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\Url;

final readonly class Entry
{
    /**
     * @param list<Priority> $flags
     */
    public function __construct(
        public string $title,
        #[Url]
        public string $link = '',
        public Priority $priority = Priority::Low,
        #[ListOf(Priority::class, max: 1)]
        public array $flags = [],
    ) {}
}
