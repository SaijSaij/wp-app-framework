<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronBroken\Duplicate;

/**
 * The same short name as CronBroken\Ping.
 */
final readonly class Ping
{
    public function __construct(public int $id) {}
}
