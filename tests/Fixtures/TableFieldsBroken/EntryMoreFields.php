<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableFieldsBroken;

use WebCodingCat\WpAppFramework\Table\TableFields;

#[TableFields(Entry::class)]
final class EntryMoreFields {}
