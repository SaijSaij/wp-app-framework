<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cron;

use Attribute;

/**
 * Handles a one-time WP-Cron task that CronScheduler::schedule() plans.
 *
 * The first parameter of the method is the task: a value class whose object
 * carries the data of the task. Further parameters are services. Each task class
 * has exactly one handler. The hook of the task is
 * `wp_app_framework/cron/{slug}/task/{name}`; scheduled tasks stay attached to
 * the name, so keep it when you rename the class or the method.
 *
 * @api Attributes/Cron
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class CronTask
{
    /**
     * @param string $name Lowercase letters, digits and hyphens, starting with a letter; unique among the tasks of
     *                     the theme or plugin.
     */
    public function __construct(
        public string $name,
    ) {}
}
