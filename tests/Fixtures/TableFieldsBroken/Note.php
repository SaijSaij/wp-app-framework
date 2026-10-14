<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableFieldsBroken;

use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\PrimaryKey;
use Saij\WpAppFramework\Table\Table;

#[Table('note')]
final class Note extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        protected string $text = '',
    ) {}
}
