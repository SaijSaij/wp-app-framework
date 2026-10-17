<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Rest;

use Attribute;

/**
 * Additional JSON schema keywords for a request argument of a `#[RestRoute]` method.
 *
 * WordPress validates the keywords: `minimum`/`maximum` apply to int and float
 * parameters, `minLength`/`maxLength`/`pattern`/`format` to string parameters.
 *
 * @api Attributes/REST
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Param
{
    /**
     * Every argument is optional; null leaves the keyword out of the schema.
     *
     * @param string|null    $description Describes the argument in the schema, e.g. in the answer to an `OPTIONS`
     *                                    request; any parameter type.
     * @param int|float|null $minimum     The lowest allowed value, included; int and float parameters.
     * @param int|float|null $maximum     The highest allowed value, included; int and float parameters.
     * @param int|null       $minLength   The minimum number of characters; string parameters.
     * @param int|null       $maxLength   The maximum number of characters; string parameters.
     * @param string|null    $pattern     A regular expression without delimiters that the value must match somewhere
     *                                    (anchor it with `^` and `$` to match the whole value); string parameters. The
     *                                    build reports an invalid expression.
     * @param Format|null    $format      A format WordPress validates or sanitizes the value with; string parameters.
     */
    public function __construct(
        public ?string $description = null,
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?string $pattern = null,
        public ?Format $format = null,
    ) {}
}
