<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronValid;

final class Journal
{
    /** @var list<mixed> */
    private array $entries = [];

    public function write(mixed $entry): void
    {
        $this->entries[] = $entry;
    }

    /**
     * @return list<mixed>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }
}
