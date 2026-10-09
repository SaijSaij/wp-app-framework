<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Rest;

use Attribute;

/**
 * Additional JSON schema keywords for a request argument of a `#[RestRoute]` method.
 *
 * WordPress validates the keywords: `minimum`/`maximum` apply to int and float
 * parameters, `minLength`/`maxLength`/`pattern`/`format` to string parameters.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Param
{
    public function __construct(
        public ?string $description = null,
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?string $pattern = null,
        public ?Format $format = null,
    ) {
    }
}
