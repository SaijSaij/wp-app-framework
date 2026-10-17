<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronBroken;

/**
 * A class name that gives no valid task name, on purpose.
 */
final readonly class Bad_Task
{
    public function __construct(public int $id) {}
}
