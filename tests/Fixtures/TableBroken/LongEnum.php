<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\PrimaryKey;
use Saij\WpAppFramework\Table\Table;

#[Table('long_enum')]
final class LongEnum extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        protected LongCase $case = LongCase::Long,
    ) {}
}
