<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableBroken;

use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\PrimaryKey;

final class WithoutTable extends AbstractRow
{
    public function __construct(#[PrimaryKey] protected ?int $id = null) {}
}
