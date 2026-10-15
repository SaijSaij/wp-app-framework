<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Editor\EditorTemplateFunctions;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Tests\Fixtures\Broken\InternalDependent;

final class ServiceResolverTest extends TestCase
{
    public function testInjectsInternalServicesIntoFrameworkClassesOnly(): void
    {
        /** @var array<class-string, ReflectionClass<object>> $classes */
        $classes = [
            RenderContext::class => new ReflectionClass(RenderContext::class),
            EditorTemplateFunctions::class => new ReflectionClass(EditorTemplateFunctions::class),
            InternalDependent::class => new ReflectionClass(InternalDependent::class),
        ];
        $resolver = ServiceResolver::forClasses($classes, [RenderContext::class]);

        $framework = new ReflectionMethod(EditorTemplateFunctions::class, '__construct')->getParameters()[0];
        self::assertSame(RenderContext::class, $resolver->injectedType($framework));

        $consumer = new ReflectionMethod(InternalDependent::class, '__construct')->getParameters()[0];
        self::assertNull($resolver->injectedType($consumer));
        self::assertSame(
            RenderContext::class . ' is internal to the framework and cannot be injected into classes of a theme or plugin.',
            $resolver->explain($consumer),
        );
    }
}
