<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\PrimaryKey;

final class WithoutTable extends AbstractRow
{
    public function __construct(#[PrimaryKey] protected ?int $id = null) {}
}
