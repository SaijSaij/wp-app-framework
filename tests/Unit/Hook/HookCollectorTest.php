<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Hook;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Hook\HookCollector;
use Saij\WpAppFramework\Hook\HookDefinition;
use Saij\WpAppFramework\Manifest\Discovery\ClassDiscovery;
use Saij\WpAppFramework\Tests\Fixtures\Valid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;

final class HookCollectorTest extends TestCase
{
    /**
     * @return array<string, HookDefinition>
     */
    private static function collectValid(): array
    {
        $classes = [];
        foreach (ClassDiscovery::classes(ManifestBuilderTest::fixture('Valid')) as $name) {
            $classes[$name] = new ReflectionClass($name);
        }

        $collector = new HookCollector(ServiceResolver::forClasses($classes, []));
        $hooks = [];
        foreach ($collector->collect($classes) as $hook) {
            $hooks[$hook->name] = $hook;
        }
        self::assertSame([], $collector->errors());

        return $hooks;
    }

    public function testFindsAllHooksInDeclarationOrder(): void
    {
        self::assertSame(
            ['fixture_filter', 'fixture_action', 'fixture_first', 'fixture_second'],
            array_keys(self::collectValid()),
        );
    }

    public function testFilterInjectsServicesAndMapsWordPressArguments(): void
    {
        $filter = self::collectValid()['fixture_filter'];

        self::assertTrue($filter->filter);
        self::assertSame(10, $filter->priority);
        self::assertSame(2, $filter->acceptedArgs);
        self::assertSame(
            [
                ['param' => 'value', 'index' => 0],
                ['param' => 'greeter', 'service' => Valid\Greeter::class],
                ['param' => 'context', 'index' => 1],
            ],
            $filter->arguments,
        );
    }

    public function testVoidMethodIsAnAction(): void
    {
        $action = self::collectValid()['fixture_action'];

        self::assertFalse($action->filter);
        self::assertSame(5, $action->priority);
        self::assertSame(0, $action->acceptedArgs);
    }

    public function testRepeatedAttributeAndFilterWithoutWordPressParameters(): void
    {
        $hooks = self::collectValid();

        // A filter without WordPress parameters still accepts the original value (returned on errors).
        self::assertSame(1, $hooks['fixture_first']->acceptedArgs);
        self::assertSame(20, $hooks['fixture_second']->priority);
        self::assertSame('repeated', $hooks['fixture_second']->method);
    }
}
