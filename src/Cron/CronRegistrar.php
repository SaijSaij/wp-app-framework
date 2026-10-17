<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cron;

use Closure;
use LogicException;
use Throwable;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Hook\HookRegistrar;
use WebCodingCat\WpAppFramework\Instance;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Project\NamePrefix;
use WebCodingCat\WpAppFramework\Value\ValueFactory;
use WP_Error;

/**
 * Registers the cron events and tasks of the manifest with WordPress and keeps
 * WP-Cron in line with them.
 *
 * On `init` it compares the events of the manifest (and the clean-up of the
 * cache) with what WP-Cron has scheduled: it schedules missing events,
 * reschedules events whose interval or start time changed, moves events with a
 * start time back to their local time after a change of daylight saving time or
 * of the time zone, and unschedules events and tasks the code no longer has. An
 * autoloaded option remembers the hooks of the instance, so the comparison costs
 * no database query when nothing changed. clear() removes everything; the
 * lifecycle of the instance calls it when the plugin is deactivated or the
 * site switches away from the theme.
 *
 * @internal
 *
 * @phpstan-import-type CronEventEntry from Manifest
 * @phpstan-import-type CronTaskEntry from Manifest
 * @phpstan-import-type HookEntry from Manifest
 * @phpstan-type StartTime array{interval: CronInterval, at: non-empty-string, weekday: Weekday|null}
 * @phpstan-type Rule array{schedule: non-empty-string, time: StartTime|null}
 */
#[NoService]
final readonly class CronRegistrar
{
    private const string PREFIX = 'wp_app_framework/cron/';

    /**
     * @param non-empty-string     $slug
     * @param list<CronEventEntry> $events
     * @param list<CronTaskEntry>  $tasks
     * @param Closure(): void      $cleanUp   Deletes expired cache files and compiled templates of older releases; runs daily.
     */
    public function __construct(
        private string $slug,
        private array $events,
        private array $tasks,
        private HookRegistrar $hooks,
        private Container $container,
        private ValueFactory $values,
        private bool $debug,
        private Closure $cleanUp,
    ) {}

    /**
     * Hook of a recurring event.
     *
     * @param non-empty-string $slug
     * @param non-empty-string $name
     * @return non-empty-string
     */
    public static function eventHook(string $slug, string $name): string
    {
        return self::PREFIX . $slug . '/' . $name;
    }

    /**
     * Hook of a task.
     *
     * @param non-empty-string $slug
     * @param non-empty-string $name
     * @return non-empty-string
     */
    public static function taskHook(string $slug, string $name): string
    {
        return self::PREFIX . $slug . '/task/' . $name;
    }

    /**
     * Name of the WP-Cron schedule of an interval in seconds.
     *
     * @param non-empty-string $slug
     * @return non-empty-string
     */
    public static function intervalName(string $slug, int $seconds): string
    {
        return self::PREFIX . $slug . '/every-' . $seconds;
    }

    /**
     * The hooks of the tasks by task class, for the CronScheduler of the instance.
     *
     * @param non-empty-string    $slug
     * @param list<CronTaskEntry> $tasks
     * @return array<class-string, non-empty-string>
     */
    public static function taskHooks(string $slug, array $tasks): array
    {
        $hooks = [];
        foreach ($tasks as $task) {
            $hooks[$task['task']] = self::taskHook($slug, $task['name']);
        }

        return $hooks;
    }

    public function register(): void
    {
        $this->hooks->register(array_map(fn(array $event): array => [
            'name' => self::eventHook($this->slug, $event['name']),
            'priority' => 10,
            'acceptedArgs' => 0,
            'filter' => false,
            'class' => $event['class'],
            'method' => $event['method'],
            'arguments' => $event['arguments'],
        ], $this->events));
        add_action(Instance::cacheCleanUpEvent($this->slug), $this->cleanUp, 10, 0);
        foreach ($this->tasks as $task) {
            add_action(self::taskHook($this->slug, $task['name']), fn(mixed ...$args) => $this->runTask($task, array_values($args)), 10, 1);
        }
        if (array_filter($this->events, static fn(array $event): bool => is_int($event['interval'])) !== []) {
            add_filter('cron_schedules', $this->addSchedules(...));
        }

        add_action('init', $this->synchronize(...));
    }

    /**
     * Runs the handler of a task with the task object created from the argument of the event.
     *
     * @param CronTaskEntry $task
     * @param list<mixed>   $args The arguments passed by WP-Cron: the values of the task.
     */
    private function runTask(array $task, array $args): void
    {
        try {
            $named = [$task['param'] => $this->values->create(['type' => 'object', 'class' => $task['task'], 'nullable' => false], $args[0] ?? null, 'task')];
            foreach ($task['arguments'] as $argument) {
                $named[$argument['param']] = $this->container->get($argument['service']);
            }
            $this->container->get($task['class'])->{$task['method']}(...$named);
        } catch (Throwable $exception) {
            // Without the values of the task: they may be personal data.
            HookRegistrar::rethrowOrLog($exception, $this->debug, sprintf(
                'cron task %s::%s() for %s on hook "%s"',
                $task['class'],
                $task['method'],
                $task['task'],
                self::taskHook($this->slug, $task['name']),
            ));
        }
    }

    /**
     * The intervals in seconds of the events (filter `cron_schedules`).
     *
     * @param array<string, array{interval: int, display: string}> $schedules
     * @return array<string, array{interval: int, display: string}>
     */
    private function addSchedules(array $schedules): array
    {
        foreach ($this->events as $event) {
            if (is_int($event['interval'])) {
                $schedules[self::intervalName($this->slug, $event['interval'])] = [
                    'interval' => $event['interval'],
                    /* translators: 1: number of seconds, 2: slug of the theme or plugin */
                    'display' => sprintf(__('Every %1$d seconds (%2$s)', 'wp-app-framework'), $event['interval'], $this->slug),
                ];
            }
        }

        return $schedules;
    }

    /**
     * Brings WP-Cron in line with the events and tasks of the manifest (action `init`).
     */
    public function synchronize(): void
    {
        $stored = $this->stored();
        $rules = $this->rules();
        $expected = $this->expected($rules);
        $success = true;

        foreach ($stored as $hook => $kind) {
            if (!isset($expected[$hook])) {
                $success = $this->remove($hook, $kind) && $success;
            }
        }

        $timezone = null;
        foreach ($rules as $hook => $rule) {
            $time = $rule['time'];
            // An event in its schedule is checked only for its phase, so a due event that has not run yet stays.
            $event = wp_get_scheduled_event($hook);
            $scheduled = $event !== false && $event->schedule === $rule['schedule'];
            if ($scheduled && ($time === null || CronTime::isOccurrence($event->timestamp, $time['interval'], $time['at'], $time['weekday'], $timezone ??= wp_timezone()))) {
                continue;
            }
            if ($event !== false && !$this->check(wp_clear_scheduled_hook($hook, [], true), $hook)) {
                $success = false;
                continue;
            }

            $timestamp = match (true) {
                $time === null => time(),
                // The phase is wrong (daylight saving time, time zone, start time): the nearest occurrence, even if it
                // is past, so a change of daylight saving time does not skip a run.
                $scheduled => CronTime::nearest($event->timestamp, $time['interval'], $time['at'], $time['weekday'], $timezone ??= wp_timezone()),
                default => CronTime::next(time(), $time['interval'], $time['at'], $time['weekday'], $timezone ??= wp_timezone()),
            };
            $success = $this->check(wp_schedule_event($timestamp, $rule['schedule'], $hook, [], true), $hook) && $success;
        }

        if ($success && !self::equal($stored, $expected)) {
            update_option($this->option(), $expected, true);
        }
    }

    /**
     * Removes the events and tasks of the instance and its option on the current site.
     */
    public function clear(): void
    {
        $success = true;
        foreach ($this->expected($this->rules()) + $this->stored() as $hook => $kind) {
            $success = $this->remove($hook, $kind) && $success;
        }
        if ($success) {
            delete_option($this->option());
        }
    }

    /**
     * @param 'event'|'task' $kind
     */
    private function remove(string $hook, string $kind): bool
    {
        return $this->check($kind === 'event' ? wp_clear_scheduled_hook($hook, [], true) : wp_unschedule_hook($hook, true), $hook);
    }

    /**
     * Whether a WP-Cron function succeeded; a WP_Error is thrown with debugging, else logged.
     */
    private function check(mixed $result, string $hook): bool
    {
        if (!$result instanceof WP_Error) {
            return true;
        }

        $message = sprintf('Cannot synchronize the WP-Cron hook "%s" of %s: %s (%s).', $hook, $this->slug, $result->get_error_message(), $result->get_error_code());
        if ($this->debug) {
            throw new LogicException($message);
        }
        error_log('wp-app-framework: ' . $message);

        return false;
    }

    /**
     * The events with their schedule and start time: those of the manifest and the clean-up of the cache.
     *
     * @return array<non-empty-string, Rule>
     */
    private function rules(): array
    {
        $rules = [Instance::cacheCleanUpEvent($this->slug) => ['schedule' => CronInterval::Daily->value, 'time' => null]];
        foreach ($this->events as $event) {
            $interval = $event['interval'];
            // The build allows a start time only with a WordPress interval other than hourly.
            $rules[self::eventHook($this->slug, $event['name'])] = [
                'schedule' => is_int($interval) ? self::intervalName($this->slug, $interval) : $interval,
                'time' => $event['at'] === null || is_int($interval) ? null : [
                    'interval' => CronInterval::from($interval),
                    'at' => $event['at'],
                    'weekday' => $event['weekday'] === null ? null : Weekday::from($event['weekday']),
                ],
            ];
        }

        return $rules;
    }

    /**
     * The hooks of the instance and their kind.
     *
     * @param array<non-empty-string, Rule> $rules
     * @return array<string, 'event'|'task'>
     */
    private function expected(array $rules): array
    {
        $expected = array_map(static fn(): string => 'event', $rules);
        foreach ($this->tasks as $task) {
            $expected[self::taskHook($this->slug, $task['name'])] = 'task';
        }

        return $expected;
    }

    /**
     * The hooks the option remembers.
     *
     * @return array<string, 'event'|'task'>
     */
    private function stored(): array
    {
        $stored = get_option($this->option(), []);

        return is_array($stored) ? array_filter($stored, static fn(mixed $kind, int|string $hook): bool => is_string($hook) && in_array($kind, ['event', 'task'], true), ARRAY_FILTER_USE_BOTH) : [];
    }

    /**
     * @param array<string, 'event'|'task'> $stored
     * @param array<string, 'event'|'task'> $expected
     */
    private static function equal(array $stored, array $expected): bool
    {
        ksort($stored);
        ksort($expected);

        return $stored === $expected;
    }

    /**
     * @return non-empty-string
     */
    private function option(): string
    {
        return NamePrefix::template($this->slug) . 'cron';
    }
}
