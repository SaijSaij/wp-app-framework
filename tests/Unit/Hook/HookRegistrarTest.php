<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Hook;

use ArgumentCountError;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\Hook\HookRegistrar;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Tests\Fixtures\Runtime\FailingHooks;
use WebCodingCat\WpAppFramework\Tests\Fixtures\Valid;
use WebCodingCat\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;

/**
 * @phpstan-import-type HookEntry from Manifest
 */
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
     * @return HookEntry
     */
    private static function hook(string $method, bool $filter): array
    {
        return [
            'name' => 'test_hook',
            'priority' => 10,
            'acceptedArgs' => $filter ? 1 : 0,
            'filter' => $filter,
            'class' => FailingHooks::class,
            'method' => $method,
            'arguments' => $filter ? [['param' => 'value', 'index' => 0]] : [],
        ];
    }

    private static function registrar(bool $debug): HookRegistrar
    {
        return new HookRegistrar(new Container([FailingHooks::class => []]), $debug);
    }

    /**
     * @return array{0: HookRegistrar, 1: array<string, HookEntry>, 2: Container}
     */
    private static function validManifest(): array
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($file, new ManifestGenerator()->generate(ManifestBuilderTest::buildValid()));
        $manifest = Manifest::load($file);
        unlink($file);

        $container = new Container($manifest['services']);

        return [new HookRegistrar($container, true), array_column($manifest['hooks'], null, 'name'), $container];
    }

    public function testInjectsServicesAndMapsWordPressArguments(): void
    {
        [$registrar, $hooks, $container] = self::validManifest();

        self::assertSame(
            'Value: Hello cli at noon!',
            $registrar->invoke($hooks['fixture_filter'], ['Value: ', new Valid\Context('cli')]),
        );

        self::assertNull($registrar->invoke($hooks['fixture_action'], []));
        self::assertSame(['action'], $container->get(Valid\Hooks::class)->getCalls());
    }

    public function testMissingOptionalWordPressArgumentUsesTheDefault(): void
    {
        [$registrar, $hooks] = self::validManifest();

        self::assertSame('Value: Hello nobody at noon!', $registrar->invoke($hooks['fixture_filter'], ['Value: ']));
    }

    public function testMissingRequiredWordPressArgumentFailsLikeAPlainCallback(): void
    {
        [$registrar, $hooks] = self::validManifest();

        $this->expectException(ArgumentCountError::class);

        $registrar->invoke($hooks['fixture_filter'], []);
    }

    public function testRethrowsInDebugMode(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        self::registrar(true)->invoke(self::hook('filter', true), ['original']);
    }

    public function testFilterReturnsOriginalValueAndLogsWithoutDebug(): void
    {
        $result = self::registrar(false)->invoke(self::hook('filter', true), ['original']);

        self::assertSame('original', $result);
        self::assertStringContainsString(
            'wp-app-framework: filter ' . FailingHooks::class . '::filter() on hook "test_hook" failed: RuntimeException: boom in ',
            (string) file_get_contents($this->log),
        );
    }

    public function testActionReturnsNullAndLogsWithoutDebug(): void
    {
        self::assertNull(self::registrar(false)->invoke(self::hook('action', false), []));
        self::assertStringContainsString('wp-app-framework: action ' . FailingHooks::class . '::action()', (string) file_get_contents($this->log));
    }

    public function testPassesResultThrough(): void
    {
        self::assertSame('value!', self::registrar(false)->invoke(self::hook('append', true), ['value']));
    }
}
