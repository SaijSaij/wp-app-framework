<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronValid;

use DateTimeImmutable;
use WebCodingCat\WpAppFramework\Cron\CronScheduler;
use WebCodingCat\WpAppFramework\Cron\CronTask;

final readonly class Reminders
{
    public function __construct(private CronScheduler $cron) {}

    #[CronTask('send-reminder')]
    public function send(SendReminder $task, Journal $journal): void
    {
        $journal->write($task);
    }

    public function remind(int $postId): bool
    {
        return $this->cron->schedule(new SendReminder($postId), new DateTimeImmutable('+1 hour'));
    }
}
