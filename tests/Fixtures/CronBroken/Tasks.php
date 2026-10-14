<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CronBroken;

use Saij\WpAppFramework\Cron\CronTask;
use Saij\WpAppFramework\Post\Post;

/**
 * Every build error of #[CronTask].
 */
final class Tasks
{
    #[CronTask]
    public function none(): void {}

    #[CronTask]
    public function scalar(int $id): void {}

    #[CronTask]
    public function nullable(?Plain $task): void {}

    #[CronTask]
    public function union(Plain|Other $task): void {}

    #[CronTask]
    public function mutable(Mutable $task): void {}

    #[CronTask]
    public function post(Post $task): void {}

    #[CronTask]
    public function enum(Kind $task): void {}

    #[CronTask]
    public function references(Linked $task): void {}

    #[CronTask]
    public function extra(Other $task, string $note): void {}

    #[CronTask]
    public function plain(Plain $task): void {}

    #[CronTask]
    public function plainAgain(Plain $task): void {}

    #[CronTask]
    public function ping(Ping $task): void {}

    #[CronTask]
    public function pingAgain(Duplicate\Ping $task): void {}

    #[CronTask]
    public function badTask(Bad_Task $task): void {}

    #[CronTask]
    public function returns(Plain $task): bool
    {
        return true;
    }
}
