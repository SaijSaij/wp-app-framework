<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableFieldsBroken;

use WebCodingCat\WpAppFramework\Table\TableFields;

// @phpstan-ignore argument.type (Wrong on purpose: Kind is no row class.)
#[TableFields(Kind::class)]
final class NoRowFields {}
