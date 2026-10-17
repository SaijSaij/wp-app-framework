<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Cron;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Cron\CronInterval;
use WebCodingCat\WpAppFramework\Cron\CronRegistrar;
use WebCodingCat\WpAppFramework\Cron\CronScheduler;
use WebCodingCat\WpAppFramework\Cron\CronTime;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\Hook\HookRegistrar;
use WebCodingCat\WpAppFramework\Instance;
use WebCodingCat\WpAppFramework\Tests\Fixtures\CronValid;
use WebCodingCat\WpAppFramework\Tests\Support\Hooks;
use WebCodingCat\WpAppFramework\Tests\Support\Recorded;
use WebCodingCat\WpAppFramework\Value\ValueFactory;

final class CronRegistrarTest extends TestCase
{
    private const string OPTION = 'my_theme_cron';
    private const string CLEAN_UP = 'wp_app_framework/cache/clean_up/my-theme';
    private const string SYNC = 'wp_app_framework/cron/my-theme/sync-feed';
    private const string POLL = 'wp_app_framework/cron/my-theme/poll-status';
    private const string NIGHTLY = 'wp_app_framework/cron/my-theme/nightly-report';
    private const string DIGEST = 'wp_app_framework/cron/my-theme/digest';
    private const string WEEKLY = 'wp_app_framework/cron/my-theme/weekly-summary';
    private const string REMINDER = 'wp_app_framework/cron/my-theme/task/send-reminder';
    private const string EXPORT = 'wp_app_framework/cron/my-theme/task/html-export';

    private Container $container;

    private int $cleanUps = 0;

    private string $log;

    private string|false $previousLog;

    protected function setUp(): void
    {
        $this->log = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousLog = ini_set('error_log', $this->log);
        $GLOBALS['wp_app_framework_test_timezone'] = 'Europe/Berlin';
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        unlink($this->log);
        Hooks::reset();
        unset(
            $GLOBALS['wp_app_framework_test_cron'],
            $GLOBALS['wp_app_framework_test_cron_writes'],
            $GLOBALS['wp_app_framework_test_cron_fail'],
            $GLOBALS['wp_app_framework_test_options'],
            $GLOBALS['wp_app_framework_test_autoload'],
            $GLOBALS['wp_app_framework_test_option_misses'],
            $GLOBALS['wp_app_framework_test_timezone'],
        );
    }

    /**
     * A registrar for the instance my-theme with the events and tasks of the fixture CronValid.
     */
    private function registrar(bool $debug = true, bool $events = true, bool $tasks = true): CronRegistrar
    {
        $manifest = CronCollectorTest::manifest();
        $cronTasks = $tasks ? $manifest['cronTasks'] : [];
        $this->container = new Container(
            $manifest['services'],
            [],
            [CronScheduler::class => static fn(): CronScheduler => new CronScheduler(CronRegistrar::taskHooks('my-theme', $cronTasks))],
        );

        return new CronRegistrar(
            'my-theme',
            $events ? $manifest['cronEvents'] : [],
            $cronTasks,
            new HookRegistrar($this->container, $debug),
            $this->container,
            new ValueFactory($manifest['values']),
            $debug,
            function (): void {
                $this->cleanUps++;
            },
        );
    }

    /**
     * @return object{hook: string, timestamp: int, schedule: string|false, args: array<mixed>, interval: int}
     */
    private static function event(string $hook): object
    {
        $event = wp_get_scheduled_event($hook);
        self::assertNotFalse($event, sprintf('%s is scheduled.', $hook));

        return $event;
    }

    /**
     * The scheduled events: timestamp => hook => key => event.
     *
     * @return array<mixed>
     */
    private static function cron(): array
    {
        $cron = $GLOBALS['wp_app_framework_test_cron'] ?? [];

        return is_array($cron) ? $cron : [];
    }

    private static function writes(): int
    {
        $writes = $GLOBALS['wp_app_framework_test_cron_writes'] ?? 0;

        return is_int($writes) ? $writes : 0;
    }

    private static function berlin(): DateTimeZone
    {
        return new DateTimeZone('Europe/Berlin');
    }

    public function testSchedulesEveryEventOnTheFirstRequest(): void
    {
        $registrar = $this->registrar();
        $registrar->register();
        $registrar->synchronize();

        self::assertSame('daily', self::event(self::CLEAN_UP)->schedule);
        self::assertEqualsWithDelta(time(), self::event(self::CLEAN_UP)->timestamp, 5);
        self::assertSame('hourly', self::event(self::SYNC)->schedule);
        self::assertEqualsWithDelta(time(), self::event(self::SYNC)->timestamp, 5);
        self::assertSame('wp_app_framework/cron/my-theme/every-900', self::event(self::POLL)->schedule);
        self::assertSame(900, self::event(self::POLL)->interval ?? null);
        // Events with a start time begin at their next occurrence in the time zone of the site.
        $nightly = self::event(self::NIGHTLY);
        self::assertSame('daily', $nightly->schedule);
        self::assertGreaterThan(time() - 5, $nightly->timestamp);
        self::assertSame('03:00', new DateTimeImmutable('@' . $nightly->timestamp)->setTimezone(self::berlin())->format('H:i'));
        self::assertTrue(CronTime::isOccurrence(self::event(self::DIGEST)->timestamp, CronInterval::TwiceDaily, '06:30', null, self::berlin()));
        self::assertSame('1 08:00', new DateTimeImmutable('@' . self::event(self::WEEKLY)->timestamp)->setTimezone(self::berlin())->format('N H:i'));
        self::assertSame(
            [
                self::CLEAN_UP => 'event',
                self::SYNC => 'event',
                self::POLL => 'event',
                self::NIGHTLY => 'event',
                self::DIGEST => 'event',
                self::WEEKLY => 'event',
                self::EXPORT => 'task',
                self::REMINDER => 'task',
            ],
            get_option(self::OPTION),
        );
        // Autoloaded: read with all options, without a query of its own.
        self::assertTrue(array_key_exists(self::OPTION, wp_load_alloptions()));
    }

    public function testWritesNothingWhenNothingChanged(): void
    {
        $registrar = $this->registrar();
        $registrar->register();
        $registrar->synchronize();
        $writes = self::writes();
        $options = get_option(self::OPTION);
        $updates = did_action('update_option_' . self::OPTION);
        unset($GLOBALS['wp_app_framework_test_option_misses']);

        $registrar->synchronize();

        self::assertSame($writes, self::writes());
        self::assertSame($options, get_option(self::OPTION));
        self::assertSame($updates, did_action('update_option_' . self::OPTION));
        self::assertFalse(isset($GLOBALS['wp_app_framework_test_option_misses']));
    }

    public function testAChangedIntervalReschedulesTheEvent(): void
    {
        wp_schedule_event(time() + 1000, 'daily', self::SYNC);
        // A single event of the hook (schedule false) is replaced as well.
        wp_schedule_single_event(time() + 2000, self::POLL);
        $registrar = $this->registrar();
        $registrar->register();

        $registrar->synchronize();

        self::assertSame('hourly', self::event(self::SYNC)->schedule);
        self::assertEqualsWithDelta(time(), self::event(self::SYNC)->timestamp, 5);
        self::assertSame('wp_app_framework/cron/my-theme/every-900', self::event(self::POLL)->schedule);
    }

    public function testAChangedStartTimeMovesTheEventToTheNearestOccurrence(): void
    {
        // Scheduled for 05:00 (an earlier at), tomorrow: 03:00 on the same day is nearest.
        $five = new DateTimeImmutable('tomorrow 05:00', self::berlin())->getTimestamp();
        wp_schedule_event($five, 'daily', self::NIGHTLY);
        $registrar = $this->registrar();
        $registrar->register();

        $registrar->synchronize();

        self::assertSame(new DateTimeImmutable('tomorrow 03:00', self::berlin())->getTimestamp(), self::event(self::NIGHTLY)->timestamp);
    }

    public function testAChangedTimeZoneMovesTheEvent(): void
    {
        $registrar = $this->registrar();
        $registrar->register();
        $registrar->synchronize();
        $GLOBALS['wp_app_framework_test_timezone'] = 'America/New_York';

        $registrar->synchronize();

        $nightly = self::event(self::NIGHTLY)->timestamp;
        self::assertTrue(CronTime::isOccurrence($nightly, CronInterval::Daily, '03:00', null, new DateTimeZone('America/New_York')));
        self::assertSame('03:00', new DateTimeImmutable('@' . $nightly)->setTimezone(new DateTimeZone('America/New_York'))->format('H:i'));
        self::assertSame('1 08:00', new DateTimeImmutable('@' . self::event(self::WEEKLY)->timestamp)->setTimezone(new DateTimeZone('America/New_York'))->format('N H:i'));
    }

    public function testAnEventOffItsPhaseAfterAChangeOfDaylightSavingTimeMovesBack(): void
    {
        // WP-Cron rescheduled 03:00 CET by a day in UTC: 04:00 CEST on 29 March 2026; back to 03:00 CEST, though past.
        wp_schedule_event(new DateTimeImmutable('2026-03-29 02:00', new DateTimeZone('UTC'))->getTimestamp(), 'daily', self::NIGHTLY);
        $registrar = $this->registrar();
        $registrar->register();

        $registrar->synchronize();

        self::assertSame(new DateTimeImmutable('2026-03-29 03:00', self::berlin())->getTimestamp(), self::event(self::NIGHTLY)->timestamp);
    }

    public function testADueEventInItsPhaseStays(): void
    {
        $due = new DateTimeImmutable('yesterday 03:00', self::berlin())->getTimestamp();
        wp_schedule_event($due, 'daily', self::NIGHTLY);
        $registrar = $this->registrar();
        $registrar->register();

        $registrar->synchronize();

        self::assertSame($due, self::event(self::NIGHTLY)->timestamp);
    }

    public function testUnschedulesEventsAndTasksTheCodeNoLongerHas(): void
    {
        $registrar = $this->registrar();
        $registrar->register();
        $registrar->synchronize();
        new CronScheduler([CronValid\SendReminder::class => self::REMINDER])->schedule(new CronValid\SendReminder(5), new DateTimeImmutable('+1 hour'));
        Hooks::reset();

        // The next version of the code has no events and no tasks.
        $registrar = $this->registrar(events: false, tasks: false);
        $registrar->register();
        $registrar->synchronize();

        foreach ([self::SYNC, self::POLL, self::NIGHTLY, self::DIGEST, self::WEEKLY] as $hook) {
            self::assertFalse(wp_get_scheduled_event($hook), $hook);
        }
        self::assertSame([self::CLEAN_UP], array_keys(array_merge(...array_values(array_filter(self::cron(), is_array(...))))));
        self::assertSame([self::CLEAN_UP => 'event'], get_option(self::OPTION));
    }

    public function testRegistersTheIntervalsInSeconds(): void
    {
        $this->registrar()->register();

        self::assertSame(['interval' => 900, 'display' => 'Every 900 seconds (my-theme)'], wp_get_schedules()['wp_app_framework/cron/my-theme/every-900'] ?? null);
    }

    public function testRegistersNoIntervalsWithoutEventsInSeconds(): void
    {
        $this->registrar(events: false)->register();

        self::assertSame([], Recorded::listFilters('cron_schedules'));
    }

    public function testAnErrorOfWordPressIsThrownWithDebugging(): void
    {
        $registrar = $this->registrar();
        $registrar->register();
        $GLOBALS['wp_app_framework_test_cron_fail'] = true;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot synchronize the WP-Cron hook "' . self::CLEAN_UP . '" of my-theme: The cron event list could not be saved. (could_not_set).');

        $registrar->synchronize();
    }

    public function testAnErrorOfWordPressIsLoggedWithoutDebuggingAndRetriedOnTheNextRequest(): void
    {
        $registrar = $this->registrar(debug: false);
        $registrar->register();
        $GLOBALS['wp_app_framework_test_cron_fail'] = true;

        $registrar->synchronize();

        self::assertStringContainsString('wp-app-framework: Cannot synchronize the WP-Cron hook "' . self::SYNC . '" of my-theme: The cron event list could not be saved. (could_not_set).', (string) file_get_contents($this->log));
        self::assertFalse(get_option(self::OPTION));

        unset($GLOBALS['wp_app_framework_test_cron_fail']);
        $registrar->synchronize();

        self::assertSame('hourly', self::event(self::SYNC)->schedule);
        self::assertIsArray(get_option(self::OPTION));
    }

    public function testClearRemovesEverything(): void
    {
        $registrar = $this->registrar();
        $registrar->register();
        $registrar->synchronize();
        new CronScheduler([CronValid\SendReminder::class => self::REMINDER])->schedule(new CronValid\SendReminder(5), new DateTimeImmutable('+1 hour'));

        $registrar->clear();

        self::assertSame([], self::cron());
        self::assertFalse(get_option(self::OPTION));
    }

    public function testEventHandlersGetServicesAndNoArguments(): void
    {
        $this->registrar()->register();

        $acceptedArgs = $GLOBALS['wp_app_framework_test_filter_args'] ?? [];
        self::assertSame([0], is_array($acceptedArgs) ? $acceptedArgs[self::SYNC] ?? null : null);
        do_action(self::SYNC, 'ignored');
        do_action(self::POLL);

        self::assertSame(['sync', 'poll'], $this->container->get(CronValid\Reports::class)->getCalls());
        self::assertSame(['sync'], $this->container->get(CronValid\Journal::class)->getEntries());
    }

    public function testTheCleanUpOfTheCacheRunsOnItsEvent(): void
    {
        $this->registrar()->register();

        do_action(self::CLEAN_UP);

        self::assertSame(1, $this->cleanUps);
        self::assertSame(self::CLEAN_UP, Instance::cacheCleanUpEvent('my-theme'));
    }

    public function testTaskHandlersGetTheTypedTask(): void
    {
        $this->registrar()->register();

        do_action(self::REMINDER, ['postId' => 5, 'kind' => 'sms', 'delay' => 2.0, 'recipient' => ['email' => 'a@example.test', 'name' => null], 'copies' => []]);
        do_action(self::EXPORT, []);

        self::assertEquals(
            [new CronValid\SendReminder(5, CronValid\ReminderKind::Sms, 2.0, new CronValid\Recipient('a@example.test'))],
            $this->container->get(CronValid\Journal::class)->getEntries(),
        );
        self::assertSame(1, $this->container->get(CronValid\Exports::class)->getRuns());
    }

    public function testATaskThatNoLongerMatchesItsClassFailsWithDebugging(): void
    {
        $this->registrar()->register();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value "task.postId" must be int, string given.');

        do_action(self::REMINDER, ['postId' => '5']);
    }

    public function testATaskThatNoLongerMatchesItsClassIsLoggedWithoutItsValues(): void
    {
        $this->registrar(debug: false)->register();

        do_action(self::REMINDER, ['postId' => 'secret@example.test']);
        // Run by hand without arguments (wp cron event run).
        do_action(self::REMINDER);

        $log = (string) file_get_contents($this->log);
        $prefix = 'wp-app-framework: cron task ' . CronValid\Reminders::class . '::send() for ' . CronValid\SendReminder::class . ' on hook "' . self::REMINDER . '" failed: InvalidArgumentException: ';
        self::assertStringContainsString($prefix . 'Value "task.postId" must be int, string given.', $log);
        self::assertStringContainsString($prefix . 'Value "task" must be an object of ' . CronValid\SendReminder::class . ', null given.', $log);
        self::assertStringNotContainsString('secret', $log);
    }
}
