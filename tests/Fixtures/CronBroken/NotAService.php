<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronBroken;

use WebCodingCat\WpAppFramework\Cron\CronEvent;
use WebCodingCat\WpAppFramework\Cron\CronInterval;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

#[NoService]
final class NotAService
{
    #[CronEvent('excluded', CronInterval::Hourly)]
    public function run(): void {}
}
