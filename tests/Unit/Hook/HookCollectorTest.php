<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Hook;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\DependencyInjection\ServiceResolver;
use WebCodingCat\WpAppFramework\Hook\HookCollector;
use WebCodingCat\WpAppFramework\Hook\HookDefinition;
use WebCodingCat\WpAppFramework\Tests\Fixtures\Valid;
use WebCodingCat\WpAppFramework\Tests\Support\Reflection;
use WebCodingCat\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;

final class HookCollectorTest extends TestCase
{
    /**
     * @return array<string, HookDefinition>
     */
    private static function collectValid(): array
    {
        $classes = Reflection::classes(ManifestBuilderTest::fixture('Valid'));

        $collector = new HookCollector(ServiceResolver::forClasses($classes, []));
        $hooks = [];
        foreach ($collector->collect($classes) as $hook) {
            $hooks[$hook->getName()] = $hook;
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

        self::assertTrue($filter->isFilter());
        self::assertSame(10, $filter->getPriority());
        self::assertSame(2, $filter->getAcceptedArgs());
        self::assertSame(
            [
                ['param' => 'value', 'index' => 0],
                ['param' => 'greeter', 'service' => Valid\Greeter::class],
                ['param' => 'context', 'index' => 1],
            ],
            $filter->getArguments(),
        );
    }

    public function testVoidMethodIsAnAction(): void
    {
        $action = self::collectValid()['fixture_action'];

        self::assertFalse($action->isFilter());
        self::assertSame(5, $action->getPriority());
        self::assertSame(0, $action->getAcceptedArgs());
    }

    public function testRepeatedAttributeAndFilterWithoutWordPressParameters(): void
    {
        $hooks = self::collectValid();

        // A filter without WordPress parameters still accepts the original value (returned on errors).
        self::assertSame(1, $hooks['fixture_first']->getAcceptedArgs());
        self::assertSame(20, $hooks['fixture_second']->getPriority());
        self::assertSame('repeated', $hooks['fixture_second']->getMethod());
    }
}
