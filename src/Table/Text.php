<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Table;

use Attribute;

/**
 * Stores a string column as long text (`longtext`, up to 4 GB) instead of `varchar(191)`. Long text has no default
 * in the database and cannot be indexed.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Text {}
