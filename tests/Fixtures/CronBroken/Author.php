<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronBroken;

use WebCodingCat\WpAppFramework\Value\UserReference;

final readonly class Author
{
    public function __construct(
        public string $name,
        #[UserReference]
        public ?int $user = null,
    ) {}
}
