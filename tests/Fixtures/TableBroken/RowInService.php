<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableBroken;

use WebCodingCat\WpAppFramework\Hook\Hook;

final class RowInService
{
    #[Hook('init')]
    public function run(ValidFields $fields): void {}
}
