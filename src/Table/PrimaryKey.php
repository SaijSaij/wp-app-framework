<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Table;

use Attribute;

/**
 * Marks the primary key of a row class: one `?int` parameter with the default null, stored as an unsigned
 * auto-increment column. save() sets it when it inserts the row.
 *
 * @api Attributes/Tables
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class PrimaryKey {}
