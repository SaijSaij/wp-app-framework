<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronBroken;

use WebCodingCat\WpAppFramework\Cron\CronEvent;
use WebCodingCat\WpAppFramework\Cron\CronInterval;
use WebCodingCat\WpAppFramework\Cron\CronTask;
use WebCodingCat\WpAppFramework\Cron\Weekday;

/**
 * Every build error of #[CronEvent] (and of a method with both attributes).
 */
final class Events
{
    #[CronEvent('Bad_Name', CronInterval::Hourly)]
    public function badName(): void {}

    #[CronEvent('twice', CronInterval::Hourly)]
    public function first(): void {}

    #[CronEvent('twice', CronInterval::Daily)]
    public function second(): void {}

    #[CronEvent('hidden', CronInterval::Hourly)]
    protected function hidden(): void {}

    #[CronEvent('static', CronInterval::Hourly)]
    public static function statically(): void {}

    #[CronEvent('returns', CronInterval::Hourly)]
    public function returns(): int
    {
        return 1;
    }

    // @phpstan-ignore missingType.return (no return type on purpose)
    #[CronEvent('untyped', CronInterval::Hourly)]
    public function untyped() {}

    // A constant WordPress defines, missing in the build.
    #[CronEvent('constant', HOUR_IN_SECONDS)]
    public function constant(): void {}

    #[CronEvent('argument', CronInterval::Hourly)]
    public function argument(int $count): void {}

    #[CronEvent('variadic', CronInterval::Hourly)]
    public function variadic(Helper ...$helpers): void {}

    #[CronEvent('short', 30)]
    public function short(): void {}

    #[CronEvent('bad-at', CronInterval::Daily, at: '3:00')]
    public function badAt(): void {}

    #[CronEvent('hourly-at', CronInterval::Hourly, at: '03:00')]
    public function hourlyAt(): void {}

    #[CronEvent('seconds-at', 3600, at: '03:00')]
    public function secondsAt(): void {}

    #[CronEvent('weekly-at', CronInterval::Weekly, at: '08:00')]
    public function weeklyAt(): void {}

    #[CronEvent('daily-weekday', CronInterval::Daily, at: '08:00', weekday: Weekday::Monday)]
    public function dailyWeekday(): void {}

    #[CronEvent('weekly-weekday', CronInterval::Weekly, weekday: Weekday::Monday)]
    public function weeklyWeekday(): void {}

    #[CronEvent('both', CronInterval::Hourly)]
    #[CronTask('both')]
    public function both(Plain $task): void {}
}
