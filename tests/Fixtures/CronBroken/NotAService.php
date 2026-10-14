<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CronBroken;

use Saij\WpAppFramework\Cron\CronEvent;
use Saij\WpAppFramework\Cron\CronInterval;
use Saij\WpAppFramework\DependencyInjection\NoService;

#[NoService]
final class NotAService
{
    #[CronEvent('excluded', CronInterval::Hourly)]
    public function run(): void {}
}
