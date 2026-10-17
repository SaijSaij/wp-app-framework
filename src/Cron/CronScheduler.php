<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cron;

use DateTimeInterface;
use LogicException;
use RuntimeException;
use WebCodingCat\WpAppFramework\Value\ValueSchemaBuilder;
use WP_Error;

/**
 * Schedules one-time WP-Cron tasks of the theme or plugin. Inject it into a
 * service:
 *
 *     $this->cron->schedule(new SendReminder($postId), new DateTimeImmutable('+1 hour'));
 *
 * A task is an object of a value class with a `#[CronTask]` handler. Two tasks
 * are the same if their values are equal: WP-Cron stores the values of the task
 * as the argument of the event and finds, compares and unschedules events by
 * their arguments.
 *
 * @api Services
 */
final readonly class CronScheduler
{
    /**
     * @internal Created by the framework.
     *
     * @param array<class-string, non-empty-string> $hooks Task class => hook of its handler.
     */
    public function __construct(private array $hooks) {}

    /**
     * Schedules the task to run at the given time (or on the next request after it).
     *
     * @param object            $task The task: an object of a value class that is the first parameter of a
     *                                `#[CronTask]` handler of this theme or plugin.
     * @param DateTimeInterface $at   The time to run the task, in any time zone.
     * @return bool True if scheduled, false if WordPress already has the same task within 10 minutes of that time.
     * @throws RuntimeException If WordPress refuses the task for another reason (e.g. a time before 1970, or a plugin).
     * @throws LogicException If the task has no `#[CronTask]` handler in this theme or plugin.
     */
    public function schedule(object $task, DateTimeInterface $at): bool
    {
        $result = wp_schedule_single_event($at->getTimestamp(), $this->hook($task), self::arguments($task), true);
        if ($result instanceof WP_Error) {
            if ($result->get_error_code() === 'duplicate_event') {
                return false;
            }
            throw self::failure('schedule', $task, $result);
        }

        return $result;
    }

    /**
     * Removes every scheduled task equal to the given one.
     *
     * @param object $task The task: an object of a value class that is the first parameter of a `#[CronTask]` handler
     *                     of this theme or plugin. Equal to the task to remove: same class, equal values.
     * @return int The number of tasks removed.
     * @throws RuntimeException If WordPress cannot save the cron events.
     * @throws LogicException If the task has no `#[CronTask]` handler in this theme or plugin.
     */
    public function unschedule(object $task): int
    {
        $result = wp_clear_scheduled_hook($this->hook($task), self::arguments($task), true);
        if ($result instanceof WP_Error) {
            throw self::failure('unschedule', $task, $result);
        }

        return (int) $result;
    }

    /**
     * Whether a task equal to the given one is scheduled.
     *
     * @param object $task The task: an object of a value class that is the first parameter of a `#[CronTask]` handler
     *                     of this theme or plugin. Equal to the task to look for: same class, equal values.
     * @throws LogicException If the task has no `#[CronTask]` handler in this theme or plugin.
     */
    public function isScheduled(object $task): bool
    {
        return wp_get_scheduled_event($this->hook($task), self::arguments($task)) !== false;
    }

    /**
     * The arguments of the event: the values of the task as plain data. They depend only on the values of the object
     * (defaults included, enums as their value, value objects as arrays in constructor order), so an equal task finds
     * the same event.
     *
     * @return array{0: mixed}
     */
    private static function arguments(object $task): array
    {
        return [ValueSchemaBuilder::export($task)];
    }

    /**
     * @return non-empty-string
     */
    private function hook(object $task): string
    {
        return $this->hooks[$task::class] ?? throw new LogicException(sprintf(
            '%s is no cron task of this theme or plugin: no method has #[CronTask] with it as first parameter.',
            $task::class,
        ));
    }

    private static function failure(string $action, object $task, WP_Error $error): RuntimeException
    {
        return new RuntimeException(sprintf(
            'Cannot %s the cron task %s: %s (%s).',
            $action,
            $task::class,
            $error->get_error_message(),
            $error->get_error_code(),
        ));
    }
}
