<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CronValid;

use Saij\WpAppFramework\Cron\CronTask;

final class Exports
{
    private int $runs = 0;

    #[CronTask]
    public function export(HTMLExport $task): void
    {
        $this->runs++;
    }

    public function getRuns(): int
    {
        return $this->runs;
    }
}
