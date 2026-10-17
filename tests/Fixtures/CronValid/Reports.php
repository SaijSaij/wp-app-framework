<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronValid;

use WebCodingCat\WpAppFramework\Cron\CronEvent;
use WebCodingCat\WpAppFramework\Cron\CronInterval;
use WebCodingCat\WpAppFramework\Cron\Weekday;

final class Reports
{
    /** @var list<string> */
    private array $calls = [];

    #[CronEvent('sync-feed', CronInterval::Hourly)]
    public function sync(Journal $journal): void
    {
        $this->calls[] = 'sync';
        $journal->write('sync');
    }

    #[CronEvent('poll-status', 15 * 60)]
    public function poll(): void
    {
        $this->calls[] = 'poll';
    }

    #[CronEvent('nightly-report', CronInterval::Daily, at: '03:00')]
    public function nightly(): void
    {
        $this->calls[] = 'nightly';
    }

    #[CronEvent('digest', CronInterval::TwiceDaily, at: '06:30')]
    public function digest(): void
    {
        $this->calls[] = 'digest';
    }

    #[CronEvent('weekly-summary', CronInterval::Weekly, at: '08:00', weekday: Weekday::Monday)]
    public function weekly(): void
    {
        $this->calls[] = 'weekly';
    }

    /**
     * @return list<string>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}
