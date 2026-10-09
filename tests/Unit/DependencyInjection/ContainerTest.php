<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\DependencyInjection;

use LogicException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Tests\Fixtures\Valid\Clock;
use Saij\WpAppFramework\Tests\Fixtures\Valid\Greeter;
use stdClass;

final class ContainerTest extends TestCase
{
    public function testCreatesSharedInstancesWithInjectedServices(): void
    {
        $container = new Container([
            Clock::class => [],
            Greeter::class => ['clock' => Clock::class],
        ]);

        $greeter = $container->get(Greeter::class);
        self::assertSame($greeter, $container->get(Greeter::class));
        self::assertSame($container->get(Clock::class), $container->get(Clock::class));
        self::assertSame('Hello you at noon!', $greeter->greet('you'));
    }

    public function testReturnsPredefinedInstances(): void
    {
        $instance = new stdClass();
        $container = new Container([], [stdClass::class => $instance]);

        self::assertSame($instance, $container->get(stdClass::class));
    }

    public function testUnknownServiceThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Service ' . Clock::class . ' is not in the manifest');

        new Container([])->get(Clock::class);
    }
}
