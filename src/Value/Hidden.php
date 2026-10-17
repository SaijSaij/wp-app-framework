<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Value;

use Attribute;

/**
 * The value gets no control in the generated inspector. Not allowed on inline
 * fields (#[PlainText], #[RichText], #[Image]), which have no inspector control.
 *
 * @api Attributes/Values
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class Hidden {}
