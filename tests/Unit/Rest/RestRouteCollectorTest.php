<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Rest\RestRouteDefinition;
use Saij\WpAppFramework\Tests\Fixtures\RestValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;

final class RestRouteCollectorTest extends TestCase
{
    /**
     * @return array<string, RestRouteDefinition> By method name.
     */
    private static function routes(): array
    {
        $routes = [];
        foreach (new ManifestBuilder([ManifestBuilderTest::fixture('RestValid')])->build()->getRoutes() as $route) {
            $routes[$route->getMethod()] = $route;
        }

        return $routes;
    }

    public function testRoutesUseNamespaceAndBaseOfTheController(): void
    {
        $routes = self::routes();

        self::assertSame(['index', 'show', 'update', 'delete', 'level', 'missing', 'fail'], array_keys($routes));
        self::assertSame('fixture/v1', $routes['index']->getNamespace());
        self::assertSame('/items', $routes['index']->getRoute());
        self::assertSame(['PUT', 'PATCH'], $routes['update']->getMethods());
    }

    public function testPlaceholdersBecomeNamedGroupsDerivedFromTheType(): void
    {
        $routes = self::routes();

        self::assertSame('/items/(?P<id>\d+)', $routes['show']->getRoute());
        self::assertSame('/items/level/(?P<level>(?:1|2))', $routes['level']->getRoute());
        self::assertSame('/items/missing/(?P<slug>[^/]+)', $routes['missing']->getRoute());
    }

    public function testSchemaIsDerivedFromTypesDefaultsAndParam(): void
    {
        $routes = self::routes();

        self::assertSame(
            [
                'perPage' => ['type' => 'integer', 'default' => 10, 'description' => 'Page size', 'minimum' => 1, 'maximum' => 50],
                'variant' => ['type' => ['string', 'null'], 'enum' => ['short', 'long', null]],
                'flag' => ['type' => 'boolean', 'default' => false],
                'ratio' => ['type' => 'number', 'default' => 0.5],
            ],
            $routes['index']->getArgs(),
        );
        self::assertSame(
            [
                'id' => ['type' => 'integer', 'required' => true],
                'variant' => ['type' => 'string', 'enum' => ['short', 'long'], 'default' => 'short'],
            ],
            $routes['show']->getArgs(),
        );
        self::assertSame(
            [
                'id' => ['type' => 'integer', 'required' => true],
                'title' => ['type' => 'string', 'required' => true, 'maxLength' => 20],
                'contact' => ['type' => ['string', 'null'], 'format' => 'email'],
            ],
            $routes['update']->getArgs(),
        );
        self::assertSame(['level' => ['type' => 'integer', 'enum' => [1, 2], 'required' => true]], $routes['level']->getArgs());
    }

    public function testArgumentsMapServicesRequestAndRequestArguments(): void
    {
        self::assertSame(
            [
                ['param' => 'id', 'kind' => 'url', 'value' => ['type' => 'int', 'nullable' => false]],
                ['param' => 'title', 'kind' => 'param', 'value' => ['type' => 'string', 'nullable' => false]],
                ['param' => 'request', 'kind' => 'request'],
                ['param' => 'contact', 'kind' => 'param', 'value' => ['type' => 'string', 'nullable' => true]],
            ],
            self::routes()['update']->getArguments(),
        );
        self::assertSame(
            [
                ['param' => 'id', 'kind' => 'url', 'value' => ['type' => 'int', 'nullable' => false]],
                ['param' => 'items', 'kind' => 'service', 'class' => RestValid\ItemRepository::class],
                ['param' => 'variant', 'kind' => 'param', 'value' => ['type' => 'enum', 'class' => RestValid\Variant::class, 'nullable' => false]],
            ],
            self::routes()['show']->getArguments(),
        );
    }

    public function testPermissions(): void
    {
        $routes = self::routes();

        self::assertSame(['type' => 'public'], $routes['show']->getPermission());
        self::assertSame(['type' => 'capability', 'capability' => 'delete_posts'], $routes['delete']->getPermission());
        self::assertSame(
            [
                'type' => 'callback',
                'class' => RestValid\ItemController::class,
                'method' => 'canEdit',
                'arguments' => [
                    ['param' => 'id', 'kind' => 'url', 'value' => ['type' => 'int', 'nullable' => false]],
                    ['param' => 'items', 'kind' => 'service', 'class' => RestValid\ItemRepository::class],
                ],
            ],
            $routes['update']->getPermission(),
        );
    }

    public function testVoidAndReachableServices(): void
    {
        $routes = self::routes();
        self::assertTrue($routes['delete']->isVoid());
        self::assertFalse($routes['show']->isVoid());

        self::assertSame(
            [RestValid\ItemController::class => [], RestValid\ItemRepository::class => []],
            new ManifestBuilder([ManifestBuilderTest::fixture('RestValid')])->build()->getServices(),
        );
    }

    public function testReportsAllErrorsWithClassMethodAndParameterNames(): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('RestBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            $c = 'Saij\\WpAppFramework\\Tests\\Fixtures\\RestBroken\\BrokenRoutes';
            $ns = 'Saij\\WpAppFramework\\Tests\\Fixtures\\RestBroken\\';
            $supported = 'use int, float, string, bool, a backed enum, a value class or array with #[ListOf]';
            $expected = [
                "#[RestController] on {$ns}BadController has the invalid version \"/broken/\": use letters, digits, \".\", \"_\", \"-\" and \"/\" without leading or trailing slash, e.g. \"v1\"; the routes are registered under \"fixture/{version}\".",
                "#[RestController] on {$ns}BadController has the invalid base path \"items/\": it must be empty or start with \"/\" and not end with \"/\".",
                "#[RestRoute] on {$c}::emptyMethods() has no HTTP method.",
                "#[RestRoute] on {$c}::anonymous(): the permission callback must be a first-class callable of a public static method, e.g. new Callback(self::canEdit(...)); anonymous closures are not supported.",
                "Permission callback {$c}::notBool() of {$c}::callbackReturn() must be public and static and return bool.",
                "parameter \$other of permission callback {$c}::unknownArgument() must be a service, WP_REST_Request or a request argument of {$c}::callbackArgument() with the same name and type (the route declares string).",
                "#[RestRoute] on {$c}::ownPermission() uses {$ns}OwnPermission as permission; only new PublicAccess(), new Capability('…') or new Callback(self::method(...)) are supported.",
                "#[RestRoute] on {$c}::emptyCapability() has an empty capability.",
                "#[RestRoute] on {$c}::placeholderWithoutParameter(): placeholder {missing} in route \"/placeholder/{missing}\" has no request argument parameter \$missing.",
                "parameter \$id of {$c}::optionalPathParameter() is a path parameter and must be required: no default value and not nullable.",
                "parameter \$ratio of {$c}::floatPathParameter() is a path parameter and must be int, string or a backed enum.",
                "parameter \$list of {$c}::arrayParameter() is an array and needs #[ListOf(Item::class)] with a backed enum or a value class.",
                "parameter \$value of {$c}::untypedParameter() has no type; {$supported}.",
                "parameter \$count of {$c}::nullableWithoutDefault() is nullable and needs a default value: WordPress treats null as a missing argument.",
                "#[Param] on parameter \$name of {$c}::paramMismatch(): minimum does not apply to string arguments.",
                "#[RestRoute] on {$c}::invalidCharacters(): route \"/invalid path\" contains characters other than letters, digits, \".\", \"_\", \"~\", \"-\", \"/\" and {name} placeholders.",
                "#[RestRoute] on {$c}::noLeadingSlash() has the invalid path \"no-slash\": it must be empty or start with \"/\".",
                "Duplicate REST route GET /fixture/v1/duplicate in {$c}::first() and {$c}::second().",
                "{$c}::staticRoute() has #[RestRoute] and must be public and non-static.",
                "{$ns}NoController has #[RestRoute] methods, but no #[RestController] attribute defining their REST namespace.",
                "#[RestRoute] on {$ns}NoPermission::a() has no permission. Pass new PublicAccess(), new Capability('…') or new Callback(self::method(...)) as permission.",
            ];

            $errors = $exception->getErrors();
            foreach ($expected as $message) {
                self::assertContains($message, $errors);
            }

            $patternErrors = array_values(array_filter($errors, static fn(string $error): bool => str_contains($error, 'badPattern()')));
            self::assertCount(1, $patternErrors);
            self::assertStringStartsWith("#[Param] on parameter \$name of {$c}::badPattern(): pattern \"(\" is not a valid regular expression: preg_match(): Compilation failed: missing closing parenthesis", $patternErrors[0]);

            self::assertCount(count($expected) + 1, $errors);
        }
    }
}
