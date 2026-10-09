<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Rest;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Rest\RestException;
use Saij\WpAppFramework\Rest\RestRegistrar;
use Saij\WpAppFramework\Tests\Fixtures\RestValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Value\ValueFactory;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Runs the routes of a generated manifest through the registrar, with test
 * doubles of the WordPress REST classes (tests/Stubs/wordpress.php).
 *
 * @phpstan-import-type RouteEntry from Manifest
 */
final class RestRegistrarTest extends TestCase
{
    private string $file;
    private string $log;
    private string|false $previousLog;

    /** @var array<string, RouteEntry> By method name. */
    private array $routes = [];

    private Container $container;

    private ValueFactory $values;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        $this->log = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousLog = ini_set('error_log', $this->log);

        file_put_contents($this->file, new ManifestGenerator()->generate(
            new ManifestBuilder([ManifestBuilderTest::fixture('RestValid')])->build(),
        ));
        $manifest = Manifest::load($this->file);

        foreach ($manifest['routes'] as $route) {
            $this->routes[$route['method']] = $route;
        }
        $this->container = new Container($manifest['services']);
        $this->values = new ValueFactory($manifest['values']);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        unlink($this->log);
        unlink($this->file);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $urlParams
     */
    private function respond(string $method, array $params = [], array $urlParams = [], bool $debug = false): mixed
    {
        return new RestRegistrar($this->container, $this->values, $debug)->respond($this->routes[$method], new WP_REST_Request($params, $urlParams));
    }

    public function testMissingOptionalArgumentsUseParameterDefaults(): void
    {
        self::assertSame(
            ['perPage' => 10, 'variant' => null, 'flag' => false, 'ratio' => 0.5],
            $this->respond('index'),
        );
    }

    public function testPresentArgumentsAreMappedByNameAndEnumsConverted(): void
    {
        self::assertSame(
            ['perPage' => 5, 'variant' => RestValid\Variant::Long, 'flag' => true, 'ratio' => 0.25],
            $this->respond('index', ['perPage' => 5, 'variant' => 'long', 'flag' => true, 'ratio' => 0.25]),
        );
        self::assertSame(['perPage' => 10, 'variant' => null, 'flag' => false, 'ratio' => 0.5], $this->respond('index', ['variant' => null]));
        self::assertSame(2, $this->respond('level', [], ['level' => 2]));
    }

    public function testPathParametersComeFromTheUrlEvenIfAQueryArgumentHasTheSameName(): void
    {
        self::assertSame(
            ['id' => 5, 'title' => 'Item 5', 'variant' => RestValid\Variant::Short],
            $this->respond('show', ['id' => 7], ['id' => 5]),
        );
    }

    public function testReturnedResponseAndRequestParameter(): void
    {
        $response = $this->respond('update', ['title' => 'New'], ['id' => 3]);

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(201, $response->get_status());
        self::assertSame(['id' => 3, 'title' => 'New', 'contact' => null, 'request' => WP_REST_Request::class], $response->get_data());
    }

    public function testVoidRouteRespondsWith204(): void
    {
        $response = $this->respond('delete', [], ['id' => 9]);

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(204, $response->get_status());
        self::assertNull($response->get_data());
        self::assertSame([9], $this->container->get(RestValid\ItemRepository::class)->getDeleted());
    }

    public function testRestExceptionBecomesWpErrorWithStatus(): void
    {
        $error = $this->respond('missing', [], ['slug' => 'nope']);

        self::assertInstanceOf(WP_Error::class, $error);
        self::assertSame('fixture_not_found', $error->get_error_code());
        self::assertSame('No item "nope".', $error->get_error_message());
        self::assertSame(['status' => 404, 'slug' => 'nope'], $error->get_error_data());
        self::assertSame('', (string) file_get_contents($this->log));
    }

    public function testUnexpectedExceptionIsLoggedAndAnswersWith500WithoutDetails(): void
    {
        $error = $this->respond('fail');

        self::assertInstanceOf(WP_Error::class, $error);
        self::assertSame(RestRegistrar::INTERNAL_ERROR_CODE, $error->get_error_code());
        self::assertSame('Internal server error.', $error->get_error_message());
        self::assertSame(['status' => 500], $error->get_error_data());
        self::assertStringContainsString(
            'wp-app-framework: REST route POST /fixture/v1/items/fail (' . RestValid\ItemController::class . '::fail()) callback failed: RuntimeException: fixture failure in ',
            (string) file_get_contents($this->log),
        );
    }

    public function testUnexpectedExceptionIncludesDetailsInDebugMode(): void
    {
        $error = $this->respond('fail', debug: true);

        self::assertInstanceOf(WP_Error::class, $error);
        $data = $error->get_error_data();
        self::assertIsArray($data);
        self::assertSame(500, $data['status']);
        self::assertSame('RuntimeException', $data['exception']['class']);
        self::assertSame('fixture failure', $data['exception']['message']);
        self::assertStringEndsWith('ItemController.php', $data['exception']['file']);
        self::assertNotEmpty($data['exception']['trace']);
    }

    public function testPermissionCallbackReceivesUrlParametersAndServices(): void
    {
        $callback = new RestRegistrar($this->container, $this->values, false)->permissionCallback($this->routes['update']);

        self::assertTrue($callback(new WP_REST_Request([], ['id' => 1])));
        self::assertFalse($callback(new WP_REST_Request([], ['id' => 13])));
    }

    public function testPermissionCallbackArgumentsAreValidatedAndSanitizedAgainstItsOwnRoute(): void
    {
        // rest_send_allow_header() calls the callbacks of all handlers with a request that was
        // sanitized for another handler, or not at all.
        $callback = new RestRegistrar($this->container, $this->values, false)->permissionCallback($this->routes['update']);

        self::assertTrue($callback(new WP_REST_Request([], ['id' => '1'])));
        self::assertFalse($callback(new WP_REST_Request([], ['id' => '13'])));
        self::assertFalse($callback(new WP_REST_Request([], ['id' => 'abc'])));
        self::assertFalse($callback(new WP_REST_Request()));
        self::assertSame('', (string) file_get_contents($this->log));
    }

    public function testPublicRouteIsAlwaysPermitted(): void
    {
        self::assertTrue(new RestRegistrar($this->container, $this->values, false)->permissionCallback($this->routes['show'])());
    }

    public function testRestExceptionNeedsAnErrorStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RestException "fixture_redirect" needs an HTTP error status between 400 and 599, 302 given.');

        new RestException('fixture_redirect', 'Moved', 302);
    }
}
