<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableBroken;

use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\PrimaryKey;
use WebCodingCat\WpAppFramework\Table\Table;

#[Table('primary_keys')]
final class PrimaryKeys extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        #[PrimaryKey]
        protected ?int $other = null,
    ) {}
}
