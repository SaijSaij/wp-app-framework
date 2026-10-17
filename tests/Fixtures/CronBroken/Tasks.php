<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronBroken;

use WebCodingCat\WpAppFramework\Cron\CronTask;
use WebCodingCat\WpAppFramework\Post\Post;

/**
 * Every build error of #[CronTask].
 */
final class Tasks
{
    // @phpstan-ignore arguments.count (no name on purpose)
    #[CronTask]
    public function unnamed(Plain $task): void {}

    #[CronTask('none')]
    public function none(): void {}

    #[CronTask('scalar')]
    public function scalar(int $id): void {}

    #[CronTask('nullable')]
    public function nullable(?Plain $task): void {}

    #[CronTask('union')]
    public function union(Plain|Other $task): void {}

    #[CronTask('mutable')]
    public function mutable(Mutable $task): void {}

    #[CronTask('post')]
    public function post(Post $task): void {}

    #[CronTask('enum')]
    public function enum(Kind $task): void {}

    #[CronTask('references')]
    public function references(Linked $task): void {}

    #[CronTask('extra')]
    public function extra(Other $task, string $note): void {}

    #[CronTask('plain')]
    public function plain(Plain $task): void {}

    #[CronTask('plain-again')]
    public function plainAgain(Plain $task): void {}

    #[CronTask('ping')]
    public function ping(Ping $task): void {}

    #[CronTask('ping')]
    public function pingAgain(Duplicate\Ping $task): void {}

    #[CronTask('bad_task')]
    public function badTask(Bad_Task $task): void {}

    #[CronTask('returns')]
    public function returns(Plain $task): bool
    {
        return true;
    }
}
