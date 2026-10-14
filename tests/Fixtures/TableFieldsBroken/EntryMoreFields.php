<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableFieldsBroken;

use Saij\WpAppFramework\Table\TableFields;

#[TableFields(Entry::class)]
final class EntryMoreFields {}
