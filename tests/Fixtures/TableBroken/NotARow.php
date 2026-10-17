<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableBroken;

use WebCodingCat\WpAppFramework\Table\Table;

#[Table('not_a_row')]
final class NotARow
{
    public function __construct(protected ?int $id = null) {}
}
