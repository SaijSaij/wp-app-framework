<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\WpCliValid;

/**
 * Records the calls of the commands; counts its instances to show that services are resolved only when a command runs.
 */
final class Importer
{
    private static int $instances = 0;

    /** @var list<array<string, mixed>> */
    private static array $calls = [];

    public function __construct()
    {
        self::$instances++;
    }

    public static function reset(): void
    {
        self::$instances = 0;
        self::$calls = [];
    }

    public static function getInstances(): int
    {
        return self::$instances;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getCalls(): array
    {
        return self::$calls;
    }

    /**
     * @param array<string, mixed> $call
     */
    public function record(array $call): void
    {
        self::$calls[] = $call;
    }
}
