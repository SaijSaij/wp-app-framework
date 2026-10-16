<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Rest\RestRegistrar;
use Saij\WpAppFramework\Tests\Fixtures\RestList;
use Saij\WpAppFramework\Tests\Support\Requests;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Value\ValueFactory;

/**
 * REST arguments with #[ListOf]: schema derivation and conversion share the
 * value schema builder and factory with block attributes.
 *
 * @phpstan-import-type RouteEntry from Manifest
 */
final class RestListTest extends TestCase
{
    public function testListSchemaAndRuntimeArguments(): void
    {
        $route = new ManifestBuilder([ManifestBuilderTest::fixture('RestList')])->build()->getRoutes()[0];

        self::assertSame(
            [
                'entries' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'required' => true],
                            'link' => ['type' => 'string', 'format' => 'uri', 'default' => ''],
                            'priority' => ['type' => 'integer', 'enum' => [1, 2], 'default' => 1],
                            // Limits of lists in value classes are validated as well.
                            'flags' => ['type' => 'array', 'items' => ['type' => 'integer', 'enum' => [1, 2]], 'default' => [], 'maxItems' => 1],
                        ],
                    ],
                    'minItems' => 1,
                    'required' => true,
                ],
                'priorities' => ['type' => 'array', 'items' => ['type' => 'integer', 'enum' => [1, 2]], 'maxItems' => 2, 'default' => [2], 'description' => 'Filter'],
            ],
            $route->getArgs(),
        );
        self::assertSame(
            ['param' => 'priorities', 'kind' => 'param', 'value' => ['type' => 'list', 'items' => ['type' => 'enum', 'class' => RestList\Priority::class, 'nullable' => false], 'nullable' => false, 'max' => 2]],
            $route->getArguments()[1],
        );
    }

    public function testListsAreConvertedForTheRouteAndThePermissionCallback(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        try {
            file_put_contents($file, new ManifestGenerator()->generate(new ManifestBuilder([ManifestBuilderTest::fixture('RestList')])->build()));
            $manifest = Manifest::load($file);
        } finally {
            unlink($file);
        }

        $registrar = new RestRegistrar(new Container($manifest['services']), new ValueFactory($manifest['values']), false);
        $route = $manifest['routes'][0];

        self::assertEquals(
            [
                'entries' => [new RestList\Entry('First', 'https://example.org', RestList\Priority::High), new RestList\Entry('Second')],
                'priorities' => [RestList\Priority::High],
            ],
            $registrar->respond($route, Requests::create(['entries' => [['title' => 'First', 'link' => 'https://example.org', 'priority' => 2], ['title' => 'Second']]])),
        );

        $permission = $registrar->permissionCallback($route);
        self::assertTrue($permission(Requests::create(['entries' => [], 'priorities' => [2]])));
        self::assertFalse($permission(Requests::create(['entries' => [], 'priorities' => [1]])));
    }

    public function testReportsListErrors(): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('RestListBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            $c = 'Saij\\WpAppFramework\\Tests\\Fixtures\\RestListBroken\\BrokenListController';
            $ns = 'Saij\\WpAppFramework\\Tests\\Fixtures\\RestListBroken\\';
            $expected = [
                "parameter \$item of {$c}::single(): a single value class is not supported as REST argument (a class type without #[ListOf] is injected as service); use #[ListOf({$ns}Item::class)] array for a list.",
                "#[Param] on parameter \$kinds of {$c}::keyword(): minLength does not apply to list arguments.",
                "parameter \$link of {$c}::format() has #[Url] and #[Param(format:)]; use one of them.",
                "parameter \$kinds of permission callback {$c}::allowed() must be a service, WP_REST_Request or a request argument of {$c}::callback() with the same name and type (the route declares array<{$ns}Kind>).",
            ];

            self::assertSame($expected, $exception->getErrors());
        }
    }
}
