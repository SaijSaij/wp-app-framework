<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Cron;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Cache\CacheStore;
use Saij\WpAppFramework\Cron\CronCollector;
use Saij\WpAppFramework\Cron\CronScheduler;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Tests\Fixtures\CronBroken;
use Saij\WpAppFramework\Tests\Fixtures\CronValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateRenderer;

/**
 * #[CronEvent] and #[CronTask] in the build: the manifest entries and every error.
 *
 * @phpstan-import-type ManifestData from Manifest
 */
final class CronCollectorTest extends TestCase
{
    /**
     * The manifest of the fixture CronValid, built with the framework like a theme.
     *
     * @return ManifestData
     */
    public static function manifest(): array
    {
        $result = new ManifestBuilder(
            [ManifestBuilderTest::fixture('CronValid'), ManifestBuilder::frameworkSource()],
            [],
            [Theme::class, TemplateRenderer::class, RenderContext::class, CacheStore::class, CronScheduler::class],
            slug: 'my-theme',
        )->build();
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, new ManifestGenerator()->generate($result));
        $manifest = Manifest::load($file);
        unlink($file);

        return $manifest;
    }

    public function testEventsAndTasksOfTheManifest(): void
    {
        $manifest = self::manifest();
        $reports = CronValid\Reports::class;

        self::assertSame(
            [
                ['name' => 'sync-feed', 'interval' => 'hourly', 'at' => null, 'weekday' => null, 'class' => $reports, 'method' => 'sync', 'arguments' => [['param' => 'journal', 'service' => CronValid\Journal::class]]],
                ['name' => 'poll-status', 'interval' => 900, 'at' => null, 'weekday' => null, 'class' => $reports, 'method' => 'poll', 'arguments' => []],
                ['name' => 'nightly-report', 'interval' => 'daily', 'at' => '03:00', 'weekday' => null, 'class' => $reports, 'method' => 'nightly', 'arguments' => []],
                ['name' => 'digest', 'interval' => 'twicedaily', 'at' => '06:30', 'weekday' => null, 'class' => $reports, 'method' => 'digest', 'arguments' => []],
                ['name' => 'weekly-summary', 'interval' => 'weekly', 'at' => '08:00', 'weekday' => 1, 'class' => $reports, 'method' => 'weekly', 'arguments' => []],
            ],
            $manifest['cronEvents'],
        );
        self::assertSame(
            [
                ['name' => 'html-export', 'task' => CronValid\HTMLExport::class, 'class' => CronValid\Exports::class, 'method' => 'export', 'param' => 'task', 'arguments' => []],
                [
                    'name' => 'send-reminder',
                    'task' => CronValid\SendReminder::class,
                    'class' => CronValid\Reminders::class,
                    'method' => 'send',
                    'param' => 'task',
                    'arguments' => [['param' => 'journal', 'service' => CronValid\Journal::class]],
                ],
            ],
            $manifest['cronTasks'],
        );
    }

    public function testTaskClassesAreValuesAndNoServices(): void
    {
        $manifest = self::manifest();

        self::assertArrayHasKey(CronValid\SendReminder::class, $manifest['values']);
        self::assertArrayHasKey(CronValid\Recipient::class, $manifest['values']);
        self::assertSame([], $manifest['values'][CronValid\HTMLExport::class]);
        self::assertNotContains(CronValid\SendReminder::class, $manifest['candidates']);
        self::assertNotContains(CronValid\HTMLExport::class, $manifest['candidates']);
        // The handlers and their services are built; the scheduler is provided at run time.
        self::assertSame(
            [CronValid\Exports::class, CronValid\Journal::class, CronValid\Reminders::class, CronValid\Reports::class],
            array_values(array_filter(array_keys($manifest['services']), static fn(string $class): bool => str_contains($class, 'CronValid'))),
        );
        self::assertSame(['cron' => CronScheduler::class], $manifest['services'][CronValid\Reminders::class]);
    }

    public function testTheTaskNameIsTheShortClassNameInKebabCase(): void
    {
        self::assertSame('send-reminder', CronCollector::taskName(CronValid\SendReminder::class));
        self::assertSame('html-export', CronCollector::taskName(CronValid\HTMLExport::class));
        self::assertSame('task2-go', CronCollector::taskName('Task2Go'));
        self::assertSame('bad_task', CronCollector::taskName(CronBroken\Bad_Task::class));
    }

    public function testReportsEveryErrorWithItsLocation(): void
    {
        $events = CronBroken\Events::class . '::';
        $event = '#[CronEvent] on ' . $events;
        $tasks = CronBroken\Tasks::class . '::';
        $task = 'parameter $task of ' . $tasks;
        $reference = '" is a reference (#[PostReference], #[TermReference], #[UserReference] or a post class), which resolves with the visibility rules of the front end; a task stores the ID as int and the handler loads the object.';
        $expected = [
            $event . 'badName(): the name "Bad_Name" must consist of lowercase letters, digits and hyphens and start with a letter.',
            '#[CronEvent] name "twice" is used by ' . $events . 'first() and ' . $events . 'second(); each event needs a name of its own.',
            $events . 'hidden() has #[CronEvent] and must be public and non-static.',
            $events . 'statically() has #[CronEvent] and must be public and non-static.',
            $events . 'returns() has #[CronEvent] and must return void.',
            $events . 'untyped() has #[CronEvent] and must return void.',
            'Invalid #[CronEvent] on ' . $events . 'constant(): Undefined constant "Saij\WpAppFramework\Tests\Fixtures\CronBroken\HOUR_IN_SECONDS" (attribute arguments are evaluated in the build, where constants WordPress defines, such as MINUTE_IN_SECONDS, do not exist; write the number itself)',
            'parameter $count of ' . $events . 'argument(): WP-Cron passes no arguments to recurring events; the parameters of a #[CronEvent] method must be services.',
            'parameter $helpers of ' . $events . 'variadic(): variadic and by-reference parameters are not supported in cron events.',
            $event . 'short(): the interval must be at least 60 seconds, 30 given.',
            $event . 'badAt(): at must be a time of day as HH:MM (00:00 to 23:59), "3:00" given.',
            $event . 'hourlyAt(): at needs the interval CronInterval::Daily, CronInterval::TwiceDaily or CronInterval::Weekly.',
            $event . 'secondsAt(): at needs the interval CronInterval::Daily, CronInterval::TwiceDaily or CronInterval::Weekly.',
            $event . 'weeklyAt(): a weekly event with at needs a weekday.',
            $event . 'dailyWeekday(): weekday needs the interval CronInterval::Weekly and at.',
            $event . 'weeklyWeekday(): weekday needs the interval CronInterval::Weekly and at.',
            $events . 'both() has #[CronEvent] and #[CronTask]; a method is either a recurring event or the handler of a task.',
            CronBroken\NotAService::class . '::run() has #[CronEvent], but ' . CronBroken\NotAService::class . ' is marked #[NoService] and therefore not a service.',
            $tasks . 'none() has #[CronTask] and needs the task as first parameter: a value class whose object carries the data of the task.',
            'parameter $id of ' . $tasks . 'scalar(): the task must be typed with a value class, int given.',
            $task . 'nullable(): the task must not be nullable.',
            $task . 'union(): the task must be typed with a value class, ' . CronBroken\Plain::class . '|' . CronBroken\Other::class . ' given.',
            $task . 'post(): Saij\WpAppFramework\Post\Post is a post class; a task stores the ID of a post (int $postId) and the handler loads the post.',
            $task . 'references(): "task.post' . $reference,
            $task . 'references(): "task.author.user' . $reference,
            $task . 'references(): "task.authors[*].user' . $reference,
            'parameter $note of ' . $tasks . 'extra(): the parameters after the task of a #[CronTask] method must be services.',
            CronBroken\Plain::class . ' is handled by ' . $tasks . 'plain() and ' . $tasks . 'plainAgain(); each task class has exactly one #[CronTask] handler.',
            'The task classes ' . CronBroken\Ping::class . ' and ' . CronBroken\Duplicate\Ping::class . ' have the same short name, so both tasks would be named "ping"; rename one of them.',
            $task . 'badTask(): the task name "bad_task" derived from the class name ' . CronBroken\Bad_Task::class . ' must consist of lowercase letters, digits and hyphens and start with a letter; rename the class.',
            $tasks . 'returns() has #[CronTask] and must return void.',
            // Reported by the value schema builder, whose errors follow those of the collectors.
            $task . 'mutable(): value class ' . CronBroken\Mutable::class . ' must be declared readonly.',
            $task . 'enum(): ' . CronBroken\Kind::class . ' cannot be a value class: it must be a concrete readonly class.',
            'Cannot autowire parameter $plain of ' . CronBroken\Consumer::class . '::__construct(): ' . CronBroken\Plain::class . ' is a cron task and cannot be injected.',
        ];

        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('CronBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame($expected, $exception->getErrors());
        }
    }
}
