<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\Table;

#[Table('abstract_table')]
abstract class AbstractTable extends AbstractRow {}
