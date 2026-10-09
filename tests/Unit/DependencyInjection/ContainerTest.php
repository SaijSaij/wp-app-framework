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
    public function testCreatesSharedInstancesLazily(): void
    {
        $created = 0;
        $container = new Container([
            Clock::class => static function () use (&$created): Clock {
                $created++;

                return new Clock();
            },
            Greeter::class => static fn (Container $c): Greeter => new Greeter(clock: $c->get(Clock::class)),
        ]);

        self::assertSame(0, $created);
        $greeter = $container->get(Greeter::class);
        self::assertSame($greeter, $container->get(Greeter::class));
        self::assertSame($container->get(Clock::class), $container->get(Clock::class));
        self::assertSame(1, $created);
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
