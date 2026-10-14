<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableFieldsBroken;

use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\PrimaryKey;
use Saij\WpAppFramework\Table\Table;

#[Table('entry')]
final class Entry extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        protected int $seats = 0,
        protected ?int $parent = null,
        protected Kind $kind = Kind::Small,
        protected string $title = '',
    ) {}
}
