<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableValid;

/**
 * A service next to the tables: the build resolves it, row and field classes are no services.
 */
final class LogService
{
    public function count(): int
    {
        return LogEntry::query()->count();
    }
}
