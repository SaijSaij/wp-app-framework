<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\MetaFieldsBroken;

use Saij\WpAppFramework\Query\MetaFields;

#[MetaFields(Kind::class)]
final class NoMeta {}
