<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RestList;

use Saij\WpAppFramework\Value\Url;

final readonly class Entry
{
    public function __construct(
        public string $title,
        #[Url] public string $link = '',
        public Priority $priority = Priority::Low,
    ) {
    }
}
