<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerValid;

use Saij\WpAppFramework\Value\PlainText;

final readonly class TabAttributes
{
    public function __construct(
        #[PlainText]
        public string $title = '',
    ) {}
}
