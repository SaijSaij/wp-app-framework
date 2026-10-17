<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableBroken;

use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\PrimaryKey;
use WebCodingCat\WpAppFramework\Table\Table;

#[Table('long_enum')]
final class LongEnum extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        protected LongCase $case = LongCase::Long,
    ) {}
}
