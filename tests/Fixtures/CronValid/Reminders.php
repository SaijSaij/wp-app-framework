<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CronValid;

use DateTimeImmutable;
use Saij\WpAppFramework\Cron\CronScheduler;
use Saij\WpAppFramework\Cron\CronTask;

final readonly class Reminders
{
    public function __construct(private CronScheduler $cron) {}

    #[CronTask]
    public function send(SendReminder $task, Journal $journal): void
    {
        $journal->write($task);
    }

    public function remind(int $postId): bool
    {
        return $this->cron->schedule(new SendReminder($postId), new DateTimeImmutable('+1 hour'));
    }
}
