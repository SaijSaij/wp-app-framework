<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronBroken;

/**
 * Not readonly, so no value class.
 */
final class Mutable
{
    public function __construct(public int $id) {}
}
