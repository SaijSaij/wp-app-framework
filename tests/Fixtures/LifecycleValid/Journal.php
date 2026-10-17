<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\LifecycleValid;

use RuntimeException;

/**
 * Records what the lifecycle methods did, with the site they ran on; fails on request.
 */
final class Journal
{
    /** @var list<string> */
    private array $entries = [];

    private ?string $failOn = null;

    public function write(string $entry): void
    {
        if ($this->failOn === $entry) {
            throw new RuntimeException(sprintf('Writing "%s" failed.', $entry));
        }
        $this->entries[] = sprintf('%s on %d', $entry, get_current_blog_id());
    }

    public function failOn(string $entry): void
    {
        $this->failOn = $entry;
    }

    /**
     * @return list<string>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }
}
