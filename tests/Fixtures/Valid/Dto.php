<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Valid;

/** Candidate with a required scalar parameter; never injected, so no build error. */
final readonly class Dto
{
    public function __construct(public string $required)
    {
    }
}
