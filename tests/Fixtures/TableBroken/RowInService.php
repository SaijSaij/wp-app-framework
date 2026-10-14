<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

use Saij\WpAppFramework\Hook\Hook;

final class RowInService
{
    #[Hook('init')]
    public function run(ValidFields $fields): void {}
}
