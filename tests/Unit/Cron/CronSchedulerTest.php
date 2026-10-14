<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Cron;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\Cron\CronScheduler;
use Saij\WpAppFramework\Tests\Fixtures\CronValid\Recipient;
use Saij\WpAppFramework\Tests\Fixtures\CronValid\ReminderKind;
use Saij\WpAppFramework\Tests\Fixtures\CronValid\SendReminder;

final class CronSchedulerTest extends TestCase
{
    private const string HOOK = 'wp_app_framework/cron/my-theme/task/send-reminder';

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_app_framework_test_cron'], $GLOBALS['wp_app_framework_test_cron_writes'], $GLOBALS['wp_app_framework_test_cron_fail']);
    }

    private static function scheduler(): CronScheduler
    {
        return new CronScheduler([SendReminder::class => self::HOOK]);
    }

    public function testArgumentsDependOnlyOnTheValuesOfTheTask(): void
    {
        $at = new DateTimeImmutable('+1 hour');

        self::assertTrue(self::scheduler()->schedule(new SendReminder(5), $at));

        $event = wp_get_scheduled_event(self::HOOK, [['postId' => 5, 'kind' => 'mail', 'delay' => 1.0, 'recipient' => null, 'copies' => []]]);
        self::assertNotFalse($event);
        self::assertSame($at->getTimestamp(), $event->timestamp);
        self::assertFalse($event->schedule);
        // Defaults, named and positional arguments make the same task.
        self::assertTrue(self::scheduler()->isScheduled(new SendReminder(postId: 5, kind: ReminderKind::Mail)));
        self::assertTrue(self::scheduler()->isScheduled(new SendReminder(5, ReminderKind::Mail, 1.0, null, [])));
        self::assertFalse(self::scheduler()->isScheduled(new SendReminder(5, ReminderKind::Sms)));
        self::assertFalse(self::scheduler()->isScheduled(new SendReminder(6)));
    }

    public function testEnumsAndValueObjectsBecomePlainData(): void
    {
        $task = new SendReminder(7, ReminderKind::Sms, 2.5, new Recipient('a@example.test'), [new Recipient('b@example.test', 'B')]);
        self::scheduler()->schedule($task, new DateTimeImmutable('+1 hour'));

        $event = wp_get_scheduled_event(self::HOOK, [[
            'postId' => 7,
            'kind' => 'sms',
            'delay' => 2.5,
            'recipient' => ['email' => 'a@example.test', 'name' => null],
            'copies' => [['email' => 'b@example.test', 'name' => 'B']],
        ]]);
        self::assertNotFalse($event);
        self::assertTrue(self::scheduler()->isScheduled(new SendReminder(7, ReminderKind::Sms, 2.5, new Recipient('a@example.test'), [new Recipient('b@example.test', 'B')])));
    }

    public function testTheSameTaskWithinTenMinutesIsADuplicate(): void
    {
        $at = new DateTimeImmutable('+1 hour');

        self::assertTrue(self::scheduler()->schedule(new SendReminder(5), $at));
        self::assertFalse(self::scheduler()->schedule(new SendReminder(5), $at->modify('+5 minutes')));
        self::assertTrue(self::scheduler()->schedule(new SendReminder(5), $at->modify('+11 minutes')));
        self::assertTrue(self::scheduler()->schedule(new SendReminder(6), $at));
    }

    public function testOtherErrorsOfWordPressThrow(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot schedule the cron task ' . SendReminder::class . ': Event timestamp must be a valid Unix timestamp. (invalid_timestamp).');

        self::scheduler()->schedule(new SendReminder(5), new DateTimeImmutable('@0'));
    }

    public function testAClassWithoutHandlerIsAnError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(Recipient::class . ' is no cron task of this theme or plugin: no method has #[CronTask] with it as first parameter.');

        self::scheduler()->isScheduled(new Recipient('a@example.test'));
    }

    public function testUnscheduleRemovesEveryEqualTaskAndCountsThem(): void
    {
        self::scheduler()->schedule(new SendReminder(5), new DateTimeImmutable('+1 hour'));
        self::scheduler()->schedule(new SendReminder(5), new DateTimeImmutable('+2 hours'));
        self::scheduler()->schedule(new SendReminder(6), new DateTimeImmutable('+1 hour'));

        self::assertSame(2, self::scheduler()->unschedule(new SendReminder(5)));
        self::assertFalse(self::scheduler()->isScheduled(new SendReminder(5)));
        self::assertTrue(self::scheduler()->isScheduled(new SendReminder(6)));
        self::assertSame(0, self::scheduler()->unschedule(new SendReminder(5)));
    }

    public function testUnscheduleThrowsIfWordPressCannotSave(): void
    {
        self::scheduler()->schedule(new SendReminder(5), new DateTimeImmutable('+1 hour'));
        $GLOBALS['wp_app_framework_test_cron_fail'] = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot unschedule the cron task ' . SendReminder::class . ': The cron event list could not be saved. (could_not_set).');

        self::scheduler()->unschedule(new SendReminder(5));
    }
}
