<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableBroken;

use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\Table;

#[Table('no_primary_key')]
final class NoPrimaryKey extends AbstractRow
{
    public function __construct(protected ?int $id = null) {}
}
