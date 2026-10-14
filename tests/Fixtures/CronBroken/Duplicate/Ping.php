<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CronBroken\Duplicate;

/**
 * The same short name as CronBroken\Ping.
 */
final readonly class Ping
{
    public function __construct(public int $id) {}
}
