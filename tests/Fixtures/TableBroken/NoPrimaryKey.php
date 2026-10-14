<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\Table;

#[Table('no_primary_key')]
final class NoPrimaryKey extends AbstractRow
{
    public function __construct(protected ?int $id = null) {}
}
