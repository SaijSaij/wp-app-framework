<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableBroken;

use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\PrimaryKey;
use WebCodingCat\WpAppFramework\Table\Table;

#[Table('wrong_primary_key')]
final class WrongPrimaryKey extends AbstractRow
{
    public function __construct(#[PrimaryKey] protected readonly ?int $id = null) {}
}
