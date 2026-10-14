<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cron;

use Attribute;

/**
 * Handles a one-time WP-Cron task that CronScheduler::schedule() plans.
 *
 * The first parameter of the method is the task: a value class whose object
 * carries the data of the task. Further parameters are services. Each task class
 * has exactly one handler. The name of the task is the short name of its class
 * in kebab case (`SendReminder` → `send-reminder`), its hook
 * `wp_app_framework/cron/{slug}/task/{name}`; renaming the class drops the
 * tasks scheduled under the old name.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class CronTask {}
