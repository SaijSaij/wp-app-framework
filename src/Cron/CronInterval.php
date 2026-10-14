<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cron;

/**
 * The intervals WordPress defines for recurring WP-Cron events; the values are
 * the names of their schedules (`wp_get_schedules()`).
 */
enum CronInterval: string
{
    /** Every hour. */
    case Hourly = 'hourly';

    /** Every 12 hours; with `at`, at that time and 12 hours later. */
    case TwiceDaily = 'twicedaily';

    /** Every day. */
    case Daily = 'daily';

    /** Every week; with `at`, on the `weekday`. */
    case Weekly = 'weekly';
}
