<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableFieldsBroken;

use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\PrimaryKey;
use WebCodingCat\WpAppFramework\Table\Table;

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
