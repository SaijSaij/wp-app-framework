<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Hook;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Hook\HookRegistrar;

final class HookRegistrarTest extends TestCase
{
    private string $log;
    private string|false $previousLog;

    protected function setUp(): void
    {
        $this->log = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousLog = ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        unlink($this->log);
    }

    /**
     * @return array{name: string, priority: int, acceptedArgs: int, filter: bool, source: string, callback: \Closure}
     */
    private static function failingHook(bool $filter): array
    {
        return [
            'name' => 'failing_hook',
            'priority' => 10,
            'acceptedArgs' => 1,
            'filter' => $filter,
            'source' => 'Foo::bar()',
            'callback' => static fn (Container $c, array $args): mixed => throw new RuntimeException('boom'),
        ];
    }

    public function testRethrowsInDebugMode(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        new HookRegistrar(new Container([]), true)->invoke(self::failingHook(true), ['original']);
    }

    public function testFilterReturnsOriginalValueAndLogsWithoutDebug(): void
    {
        $result = new HookRegistrar(new Container([]), false)->invoke(self::failingHook(true), ['original']);

        self::assertSame('original', $result);
        self::assertStringContainsString(
            'wp-app-framework: filter Foo::bar() on hook "failing_hook" failed: RuntimeException: boom in ',
            (string) file_get_contents($this->log),
        );
    }

    public function testActionReturnsNullAndLogsWithoutDebug(): void
    {
        self::assertNull(new HookRegistrar(new Container([]), false)->invoke(self::failingHook(false), []));
        self::assertStringContainsString('wp-app-framework: action Foo::bar()', (string) file_get_contents($this->log));
    }

    public function testPassesResultThrough(): void
    {
        $hook = ['callback' => static fn (Container $c, array $args): string => $args[0] . '!'] + self::failingHook(true);

        self::assertSame('value!', new HookRegistrar(new Container([]), false)->invoke($hook, ['value']));
    }
}
