<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

use Saij\WpAppFramework\Hook\Hook;

final class UsesRow
{
    public function __construct(private Valid $valid) {}

    #[Hook('init')]
    public function run(): void
    {
        if ($this->valid->getId() === null) {
            return;
        }
    }
}
