<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\Length;
use Saij\WpAppFramework\Table\PrimaryKey;
use Saij\WpAppFramework\Table\Table;

#[Table('wide')]
final class Wide extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        #[Length(16382)]
        protected string $text = '',
    ) {}
}
