<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cron;

use Attribute;

/**
 * Runs a public method of a service as a recurring WP-Cron event.
 *
 * The method returns `void`; its parameters are services, because WP-Cron passes
 * no arguments to recurring events. The event is scheduled on the first request,
 * rescheduled when the interval, `at` or `weekday` changes and unscheduled when
 * the method loses the attribute. Its hook is `wp_app_framework/cron/{slug}/{name}`.
 *
 * `$interval` is a CronInterval or a number of seconds (at least 60). Write the
 * number itself (`15 * 60`): attribute arguments are evaluated in the build,
 * where WordPress constants such as MINUTE_IN_SECONDS do not exist.
 *
 * `$at` (`HH:MM`, in the time zone of the site) fixes the start time of daily,
 * twice daily and weekly events; weekly events with `$at` also need `$weekday`.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class CronEvent
{
    /**
     * @param string           $name     Lowercase letters, digits and hyphens, starting with a letter; unique among
     *                                   the events of the theme or plugin.
     * @param CronInterval|int $interval A WordPress interval or seconds, at least 60.
     * @param string|null      $at       Start time `HH:MM` in the time zone of the site.
     * @param Weekday|null     $weekday  Day of weekly events with `$at`.
     */
    public function __construct(
        public string $name,
        public CronInterval|int $interval,
        public ?string $at = null,
        public ?Weekday $weekday = null,
    ) {}
}
