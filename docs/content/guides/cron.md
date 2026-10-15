# Cron events and tasks

Some work does not belong to a page view. A feed is synchronized every hour, old data is cleaned up every night, a report goes out on Monday morning, a reminder is sent an hour after someone saved a post. WordPress runs such work with WP-Cron. Using it by hand takes a fair amount of code: you add an action, check with `wp_next_scheduled()` whether the event exists, schedule it, register custom intervals, and unschedule everything when the theme or plugin goes away. Small mistakes leave orphaned events behind, keep an old interval forever or let a nightly run drift by an hour twice a year.

The framework does this for you. You put `#[CronEvent]` on a method that should run regularly, and `#[CronTask]` on a method that handles a one-time task, which you plan with the `CronScheduler` service.

## How WP-Cron works

WP-Cron is no real scheduler: nothing runs at the exact time of an event. Instead, WordPress checks on every page view whether an event is due. If one is, it sends a request to its own `wp-cron.php` in the background, and that request runs the due events. On a site with few visitors, an event therefore runs late; on a site without any visitors, it does not run at all.

Busy sites often turn this off with `define('DISABLE_WP_CRON', true);` in `wp-config.php` and call `wp-cron.php` from a system cron job instead, for example every minute. Everything on this page works the same way then.

WordPress stores all scheduled events, with their arguments, in the option `cron`. The option is loaded on every request.

## Recurring events

A recurring event is a public method of a [service](./services.md) with `#[CronEvent]`. It names the event and its interval:

```php
use Saij\WpAppFramework\Cron\CronEvent;
use Saij\WpAppFramework\Cron\CronInterval;

final readonly class FeedSync
{
    #[CronEvent('sync-feed', CronInterval::Hourly)]
    public function sync(FeedClient $client, FeedStore $store): void
    {
        $store->replace($client->fetch());
    }
}
```

The method returns `void`. Its parameters are services, which the framework passes in when the event runs. WP-Cron passes no arguments to recurring events, so every parameter must be a service.

On the first request after the build, the framework schedules the event. It is due at once, runs with the next WP-Cron request and then every hour. Like hooks (see [Hooks](./hooks.md)), the service is created only when the event runs.

The interval is one of the four intervals of WordPress – `CronInterval::Hourly`, `CronInterval::TwiceDaily`, `CronInterval::Daily` and `CronInterval::Weekly` – or a number of seconds:

```php
#[CronEvent('poll-status', 15 * 60)]
public function poll(): void { /* … */ }
```

Write the number itself. Attribute arguments are evaluated in the build, where the constants WordPress defines, such as `MINUTE_IN_SECONDS`, do not exist. An interval in seconds must be at least 60.

## Fixed start times

A nightly report should run at night, not at whatever time the first request after the deployment came in. `at` fixes the start time, in the time zone of the site (Settings → General):

```php
#[CronEvent('nightly-report', CronInterval::Daily, at: '03:00')]
public function nightly(): void { /* … */ }

#[CronEvent('digest', CronInterval::TwiceDaily, at: '06:30')]   // 06:30 and 18:30
public function digest(): void { /* … */ }

#[CronEvent('weekly-summary', CronInterval::Weekly, at: '08:00', weekday: Weekday::Monday)]
public function weekly(): void { /* … */ }
```

The event is scheduled for the next occurrence of that time. A twice daily event runs at `at` and twelve hours later; a weekly event runs on its `weekday`.

WP-Cron keeps the rhythm of an event in UTC, so after a change to or from daylight saving time a 03:00 event would run at 04:00 or 02:00. The framework checks on every request whether an event with a start time still runs at its local time. If it does not – after a change of daylight saving time, after someone changed the time zone of the site, or after you changed `at` or `weekday` – it moves the event to the nearest occurrence. When that occurrence has already passed, the event runs once on the next request and then keeps its local time again. That way a change of daylight saving time never skips a run.

A start time that does not exist on a day, such as 02:30 when the clocks go forward, moves forward by the gap (03:30). In the hour that repeats when the clocks go back, the first of the two times counts.

If you remove `at` from an event and keep its interval, the event keeps the time of day it ran at so far. It goes on running in its interval, but it is no longer moved back to its local time, so after the next change of daylight saving time it runs an hour earlier or later. The comparison described below does not give it a new start time; only a change of the interval schedules it anew, due at once.

## One-time tasks

A one-time task carries data: the post a reminder is about, the kind of reminder. You describe that data as a [value class](./values.md#value-classes) and write one handler for it:

```php
final readonly class SendReminder
{
    public function __construct(
        public int $postId,
        public ReminderKind $kind = ReminderKind::Mail,
    ) {}
}
```

```php
use Saij\WpAppFramework\Cron\CronTask;

final readonly class Reminders
{
    #[CronTask('send-reminder')]
    public function send(SendReminder $task, Mailer $mailer): void
    {
        $post = get_post($task->postId);
        // …
    }
}
```

The first parameter of the handler is the task; further parameters are services. Each task class has exactly one handler. The name, here `send-reminder`, identifies the task in WordPress: keep it when you rename the class or the method.

To plan a task, inject `CronScheduler` and pass it a task object and a time:

```php
use Saij\WpAppFramework\Cron\CronScheduler;
use Saij\WpAppFramework\Hook\Hook;

final readonly class ReminderPlanner
{
    public function __construct(private CronScheduler $cron) {}

    #[Hook('save_post_event')]
    public function plan(int $postId): void
    {
        $this->cron->schedule(new SendReminder($postId), new DateTimeImmutable('+1 hour'));
    }
}
```

When the task is due, WP-Cron runs the handler with a new `SendReminder` object that has the same values. `isScheduled()` tells you whether a task is waiting, and `unschedule()` removes it again:

```php
$this->cron->isScheduled(new SendReminder($postId));   // bool
$this->cron->unschedule(new SendReminder($postId));    // number of tasks removed
```

### Equal tasks

Two task objects are the same task if their values are equal. WordPress compares events by their arguments, and the framework passes the values of the task as plain data: enums as their value, nested value objects as arrays, defaults included. So `new SendReminder(5)` and `new SendReminder(postId: 5, kind: ReminderKind::Mail)` are the same task, and `unschedule()` finds a task you planned elsewhere as long as you build an equal object.

WordPress refuses a task that equals one already planned within ten minutes of the same time; `schedule()` then returns `false`. For a time in the past or in the next ten minutes, every earlier equal task that has not run yet counts as well.

### What a task may contain

A task class is a value class with `int`, `float`, `string` and `bool` fields (also nullable), backed enums, nested value classes and `#[ListOf]` lists of them. References are not allowed, neither `#[PostReference]`, `#[TermReference]` and `#[UserReference]` nor post classes. The value factory resolves references with the rules of the front end, so a reference to a draft would become `null` when the task runs in the background. Store the ID as `int` and load the object in the handler.

The task class is data, not a service: it cannot be injected into a constructor.

:::warning Keep tasks small
WordPress keeps every planned task, with its values, in the option `cron`, which is loaded on every request. Store IDs, not content, and do not plan thousands of tasks at once: a task per post of a large import would slow down every page.
:::

## Keeping WP-Cron in line with the code

On every request (on `init`), the framework compares the events of your code with what WP-Cron has scheduled and fixes the differences:

- A new event is scheduled.
- An event whose interval changed is rescheduled.
- An event with a start time that runs off its local time moves to the nearest occurrence (see above).
- An event or task you removed from the code is unscheduled. Planned tasks of a removed task class are lost.

The framework remembers the events and tasks of your theme or plugin in an autoloaded option (`{slug}_cron`, with underscores instead of hyphens). When nothing changed, the comparison costs no database query and writes nothing.

When a plugin is deactivated, or the site switches to another theme, the framework removes all events and tasks of that plugin or theme and its option. Switching from a theme to its own child theme keeps them, because the theme stays active as parent theme. Activating the plugin or switching back to the theme schedules them again on the next request. See [Activation, deactivation and uninstallation](./lifecycle.md) for what else happens at these moments.

The daily clean-up of the [file cache](./cache.md#where-entries-are-stored) and of [compiled templates of older releases](./twig.md#cache) is an event of the same kind.

## Hook names

Each event and task has a WordPress hook, which you see in tools like WP-CLI:

| What | Hook |
|---|---|
| Recurring event | `wp_app_framework/cron/{slug}/{name}` |
| Task | `wp_app_framework/cron/{slug}/task/{name}` |
| Interval in seconds | schedule `wp_app_framework/cron/{slug}/every-{seconds}` |

The name of a task is the name in its `#[CronTask]`.

:::warning Renaming a task
The name of a task is part of what WordPress stores. If you change the name in `#[CronTask]`, the tasks planned under the old name are unscheduled on the next request and never run. Renaming the class or the method is safe.
:::

With WP-CLI you can list the events and run one by hand:

```sh
wp cron event list
wp cron event run wp_app_framework/cron/my-theme/sync-feed
```

## When things go wrong

If an event or task throws an exception, the outcome depends on `WP_DEBUG`, as with hooks. Without it, the exception is logged with `error_log()` and the other due events still run. The log names the hook, the handler and, for tasks, the task class, but not the values of the task, which may be personal data.

:::warning A failed task is gone
WordPress removes a task before it runs it, so a task that fails is not tried again. With `WP_DEBUG`, the exception also ends the WP-Cron request: the remaining due events run only with a later request.
:::

A task whose stored values no longer match its class – for example because you changed a field type while tasks were planned – fails the same way, with an `InvalidArgumentException` that names the field.

## Rules

[`#[CronEvent(name, interval, at: null, weekday: null)]`](../api/php/Cron/CronEvent.md):

| Parameter | Type | Rule |
|---|---|---|
| `name` | `string` | Required; lowercase letters, digits and hyphens, starting with a letter; unique among the events of the theme or plugin. |
| `interval` | `CronInterval\|int` | Required; an interval of WordPress or seconds, at least 60. |
| `at` | `?string` | Start time `HH:MM` (00:00 to 23:59) in the time zone of the site; only with `Daily`, `TwiceDaily` and `Weekly`. |
| `weekday` | `?Weekday` | Required for `Weekly` with `at`; only there. |

[`#[CronTask(name)]`](../api/php/Cron/CronTask.md):

| Parameter | Type | Rule |
|---|---|---|
| `name` | `string` | Required; lowercase letters, digits and hyphens, starting with a letter; unique among the tasks of the theme or plugin. |

Build errors for both:

- The class is no service, or the method is not public, is static or does not return `void`.
- A method has `#[CronEvent]` and `#[CronTask]`.
- The arguments of the attribute cannot be evaluated, for example because they use a constant of WordPress.

Build errors for `#[CronEvent]`:

- An invalid or duplicate name, an interval below 60 seconds.
- An invalid `at`, `at` with `Hourly` or seconds, `Weekly` with `at` but without `weekday`, `weekday` without `Weekly` and `at`.
- A parameter that is no service.

Build errors for `#[CronTask]`:

- No first parameter, or one that is not typed with a value class: no type, a scalar, nullable, a union, a post class, variadic or by reference.
- A task class that is no valid value class, or that contains a reference at any depth (the error names the path, such as `task.author.user`).
- A further parameter that is no service.
- Two handlers for one task class; an invalid or duplicate name.
- A task class injected into a constructor.

At run time:

- `CronScheduler::schedule(object $task, DateTimeInterface $at): bool` returns `true` if the task was planned and `false` if an equal task is already planned within ten minutes. Any other refusal of WordPress, such as a time before 1970 or a plugin that blocks the event, throws a `RuntimeException`.
- `CronScheduler::unschedule(object $task): int` returns the number of tasks removed; it throws a `RuntimeException` if WordPress cannot save the events.
- `CronScheduler::isScheduled(object $task): bool`.
- All three throw a `LogicException` for an object whose class has no `#[CronTask]` handler in this theme or plugin. `$task` is typed `object`; static analysis does not catch a wrong class.
- When WordPress cannot save the events while the framework brings them in line, the error is thrown as `LogicException` with `WP_DEBUG` and logged otherwise; the next request tries again.
