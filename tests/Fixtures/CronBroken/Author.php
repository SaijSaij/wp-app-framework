<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CronBroken;

use Saij\WpAppFramework\Value\UserReference;

final readonly class Author
{
    public function __construct(
        public string $name,
        #[UserReference]
        public ?int $user = null,
    ) {}
}
