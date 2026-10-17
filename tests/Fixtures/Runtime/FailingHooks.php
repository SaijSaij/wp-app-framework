<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Runtime;

use RuntimeException;

/**
 * Hook targets for registrar tests; not scanned by any build test.
 */
final class FailingHooks
{
    public function filter(string $value): string
    {
        throw new RuntimeException('boom');
    }

    public function action(): void
    {
        throw new RuntimeException('boom');
    }

    public function append(string $value): string
    {
        return $value . '!';
    }
}
