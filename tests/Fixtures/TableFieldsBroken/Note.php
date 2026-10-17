<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableFieldsBroken;

use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\PrimaryKey;
use WebCodingCat\WpAppFramework\Table\Table;

#[Table('note')]
final class Note extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        protected string $text = '',
    ) {}
}
