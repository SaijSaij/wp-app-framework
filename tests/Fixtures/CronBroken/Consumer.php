<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronBroken;

use WebCodingCat\WpAppFramework\Cron\CronEvent;
use WebCodingCat\WpAppFramework\Cron\CronInterval;

/**
 * Injects a task class, which is data, not a service.
 */
final readonly class Consumer
{
    public function __construct(private Plain $plain) {}

    #[CronEvent('consumer', CronInterval::Hourly)]
    public function run(): void {}

    public function getPlain(): Plain
    {
        return $this->plain;
    }
}
