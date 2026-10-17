<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\MetaFieldsBroken;

use WebCodingCat\WpAppFramework\Query\MetaFields;

#[MetaFields(Kind::class)]
final class NoMeta {}
