<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Cache;

use LogicException;
use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Asset\BuildAssets;
use WebCodingCat\WpAppFramework\Block\BlockRegistrar;
use WebCodingCat\WpAppFramework\Cache\Backend\FileBackend;
use WebCodingCat\WpAppFramework\Cache\Backend\ObjectBackend;
use WebCodingCat\WpAppFramework\Cache\Backend\TransientBackend;
use WebCodingCat\WpAppFramework\Cache\CacheStore;
use WebCodingCat\WpAppFramework\Cache\CodeGeneration;
use WebCodingCat\WpAppFramework\Cache\TagVersions;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\Editor\RenderContext;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Manifest\ManifestGenerator;
use WebCodingCat\WpAppFramework\Rest\RestRegistrar;
use WebCodingCat\WpAppFramework\Tests\Fixtures\CacheValid\Renders;
use WebCodingCat\WpAppFramework\Tests\Support\Files;
use WebCodingCat\WpAppFramework\Tests\Support\Hooks;
use WebCodingCat\WpAppFramework\Tests\Support\Recorded;
use WebCodingCat\WpAppFramework\Tests\Support\Requests;
use WebCodingCat\WpAppFramework\Twig\WordPressTemplateFunctions;
use WebCodingCat\WpAppFramework\Value\ValueFactory;
use WebCodingCat\WpAppFramework\View\ViewAssets;
use WebCodingCat\WpAppFramework\View\ViewData;
use WP_Block;
use WP_Error;
use WP_REST_Response;
use WP_User;

/**
 * Blocks and REST routes with #[Cache], and the template functions that depend on the request.
 *
 * @phpstan-import-type ManifestData from Manifest
 * @phpstan-import-type RouteEntry from Manifest
 */
final class CacheUseTest extends TestCase
{
    private string $file;

    private string $build;

    private string $log;

    private string|false $previousLog;

    /** @var ManifestData */
    private array $manifest;

    private RenderContext $context;

    private CacheStore $cache;

    private ViewData $viewData;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        $this->log = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousLog = ini_set('error_log', $this->log);
        $this->build = sys_get_temp_dir() . '/wpaf-cache-build-' . bin2hex(random_bytes(4));
        mkdir($this->build . '/vendor/wp-app-framework', 0777, true);
        file_put_contents($this->build . '/vendor/wp-app-framework/view.asset.php', "<?php return ['dependencies' => [], 'version' => 'r1'];");
        file_put_contents($this->build . '/Accordion.view.asset.php', "<?php return ['dependencies' => [], 'version' => 'a1'];");
        file_put_contents($this->file, new ManifestGenerator()->generate(CacheBuildTest::buildValid()));
        $this->manifest = Manifest::load($this->file);
        $this->context = new RenderContext();
        $this->viewData = new ViewData('my-theme', true);
        $container = new Container($this->manifest['services'], [RenderContext::class => $this->context, ViewData::class => $this->viewData]);
        $this->cache = new CacheStore(
            'my-theme',
            ['object' => new ObjectBackend('my-theme'), 'file' => new FileBackend($this->build . '/cache'), 'transient' => new TransientBackend('my_theme_')],
            new TagVersions('my_theme_'),
            new CodeGeneration(static fn(): string => 'g1'),
            $container,
        );
        add_filter('wp_app_framework/cache/backends/my-theme', static fn(): array => ['transient']);
        $GLOBALS['wp_app_framework_test_blocks'] = [];
        $GLOBALS['wp_app_framework_test_scripts'] = [];
        $GLOBALS['wp_app_framework_test_styles'] = [];
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        unlink($this->log);
        unlink($this->file);
        Files::remove($this->build);
        Hooks::reset();
        $_GET = [];
        unset(
            $GLOBALS['wp_app_framework_test_blocks'],
            $GLOBALS['wp_app_framework_test_scripts'],
            $GLOBALS['wp_app_framework_test_styles'],
            $GLOBALS['wp_app_framework_test_transients'],
            $GLOBALS['wp_app_framework_test_options'],
            $GLOBALS['wp_app_framework_test_autoload'],
            $GLOBALS['wp_app_framework_test_renders'],
            $GLOBALS['wp_app_framework_test_user'],
            $GLOBALS['wp_app_framework_test_logged_in'],
        );
    }

    private function register(bool $debug = true): void
    {
        $container = new Container($this->manifest['services'], [RenderContext::class => $this->context, ViewData::class => $this->viewData]);
        new BlockRegistrar(
            $container,
            new ValueFactory($this->manifest['values']),
            $this->context,
            $debug,
            'my-theme',
            new ViewAssets('my-theme', new BuildAssets('my-theme', $this->build, 'https://example.test/build')),
            $this->cache,
            $this->viewData,
        )->register($this->manifest['blocks']);
    }

    /**
     * @phpstan-impure Renders or reads the cache.
     * @param array<string, mixed> $attributes
     */
    private static function latest(array $attributes = [], string $content = '', int $postId = 1): string
    {
        return Recorded::renderCallback('fixture/latest')(
            ['title' => '', 'fail' => false, 'enqueue' => false, 'data' => '', ...$attributes],
            $content,
            new WP_Block(['blockName' => 'fixture/latest'], ['postId' => $postId]),
        );
    }

    /**
     * @phpstan-impure Counts renders.
     */
    private static function renders(string $name): int
    {
        return Renders::get($name);
    }

    /**
     * @phpstan-impure Responds or reads the cache.
     * @param RouteEntry           $route
     * @param array<string, mixed> $query
     */
    private static function respond(RestRegistrar $registrar, array $route, array $query = []): mixed
    {
        return $registrar->respond($route, Requests::create($query));
    }

    /**
     * Runs a call as template of a block whose output is cached with the given variants (null: not cached).
     *
     * @param list<string>|null    $cached
     * @param \Closure(): (string|bool) $call
     */
    private function inBlock(?array $cached, \Closure $call): string
    {
        return $this->context->renderBlock('fixture/x', [], false, static fn(): string => (string) $call(), cached: $cached);
    }

    public function testACachedBlockRendersOncePerInput(): void
    {
        $this->register();

        self::assertSame('<div>A|c|1</div>', self::latest(['title' => 'A'], 'c'));
        self::assertSame('<div>A|c|1</div>', self::latest(['title' => 'A'], 'c'));
        self::assertSame('<div>B|c|2</div>', self::latest(['title' => 'B'], 'c'), 'Attributes');
        self::assertSame('<div>B|d|3</div>', self::latest(['title' => 'B'], 'd'), 'Content');
        self::assertSame('<div>B|d|4</div>', self::latest(['title' => 'B'], 'd', 2), 'Context');
        $_GET['page'] = '2';
        self::assertSame('<div>B|d|5</div>', self::latest(['title' => 'B'], 'd', 2), 'Variant');
        self::assertSame('<div>B|d|5</div>', self::latest(['title' => 'B'], 'd', 2));
    }

    public function testRendersForTheEditorAndItsPreviewAreNeverCached(): void
    {
        $this->register();

        $this->context->renderForEditor(static fn(): string => self::latest());
        $this->context->renderForEditor(static fn(): string => self::latest());
        $this->context->renderForPreview(static fn(): string => self::latest());
        $this->context->renderForPreview(static fn(): string => self::latest());

        self::assertSame(4, self::renders('fixture/latest'));
        self::assertSame([], Recorded::transients());
    }

    public function testErrorsAreNotCached(): void
    {
        $this->register(debug: false);

        self::assertSame('', self::latest(['fail' => true]));
        self::assertSame('', self::latest(['fail' => true]));

        self::assertSame(2, substr_count((string) file_get_contents($this->log), 'RuntimeException: fixture cache failure'));
        self::assertSame([], Recorded::transients());
    }

    public function testACacheHitEnqueuesWhatTheRenderEnqueued(): void
    {
        $this->register();
        wp_register_style('fixture-latest', 'https://example.test/latest.css');

        self::latest(['enqueue' => true]);
        self::assertTrue(wp_style_is('fixture-latest'));

        wp_register_style('fixture-latest', 'https://example.test/latest.css');
        self::latest(['enqueue' => true]);
        self::assertSame(1, self::renders('fixture/latest'));
        self::assertTrue(wp_style_is('fixture-latest'), 'Enqueued again from the cache entry.');

        $GLOBALS['wp_app_framework_test_styles'] = [];
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Block fixture/latest is cached (#[Cache]) and enqueued the style "fixture-latest" when it rendered, but the style is not registered now that the block comes from the cache; register it before blocks render (e.g. on init) instead of in render().');
        self::latest(['enqueue' => true]);
    }

    public function testACacheHitSetsTheViewDataTheRenderSet(): void
    {
        $this->register();

        self::latest(['data' => 'First']);
        self::assertSame(['title' => 'First'], $this->viewData->get('latest'));

        // A new request: the value comes from the cache entry, without rendering.
        $this->viewData = new ViewData('my-theme', true);
        $this->register();
        self::latest(['data' => 'First']);
        self::assertSame(1, self::renders('fixture/latest'));
        self::assertSame(['title' => 'First'], $this->viewData->get('latest'));
    }

    public function testACacheHitEnqueuesTheViewScripts(): void
    {
        $this->register();
        $render = static fn(): string => Recorded::renderCallback('fixture/accordion')([], '', new WP_Block(['blockName' => 'fixture/accordion']));

        $html = $render();
        self::assertStringContainsString('data-wp-app-framework-views=', $html);
        $handles = ['my-theme-wp-app-framework-view', 'my-theme-fixture-accordion-view'];
        self::assertSame($handles, array_keys(array_filter(Recorded::scripts(), static fn(array $script): bool => $script['enqueued'])));

        // The entry holds no view scripts: they are enqueued by the framework, also from the cache.
        $transients = Recorded::transients();
        $entry = $transients[(string) array_key_first($transients)]['value'] ?? null;
        self::assertSame(['scripts' => [], 'styles' => [], 'modules' => []], is_array($entry) && is_array($entry['value']) ? $entry['value']['assets'] ?? null : null);

        $GLOBALS['wp_app_framework_test_scripts'] = [];
        self::assertSame($html, $render());
        self::assertSame($handles, array_keys(array_filter(Recorded::scripts(), static fn(array $script): bool => $script['enqueued'])));
    }

    public function testTemplateFunctionsThatDependOnTheRequestNeedAVariantInCachedBlocks(): void
    {
        $functions = new WordPressTemplateFunctions($this->context);
        $inBlock = $this->inBlock(...);

        // Not cached, or with a matching variant: allowed.
        self::assertSame('', $inBlock(null, static fn(): bool => $functions->isUserLoggedIn()));
        self::assertSame('', $inBlock(['role'], static fn(): bool => $functions->isUserLoggedIn()));
        self::assertSame('1970', $inBlock([], static fn(): string => $functions->date('Y', 0)));
        self::assertSame('1 minutes', $inBlock([], static fn(): string => $functions->humanTimeDiff(0, 60)));

        $failures = [
            'Block fixture/x is cached (#[Cache]), but its template calls wp_is_user_logged_in(), which depends on whether the visitor is logged in; add new LoggedInVary() or new RoleVary() or new UserVary() to vary of #[Cache].' => static fn(): bool => $functions->isUserLoggedIn(),
            'Block fixture/x is cached (#[Cache]), but its template calls wp_logout_url(), which depends on the session of the user (its nonce); it cannot be used in cached blocks.' => static fn(): string => $functions->logoutUrl(),
            'Block fixture/x is cached (#[Cache]), but its template calls wp_date(), which depends on the current time without a timestamp; it cannot be used in cached blocks.' => static fn(): string => $functions->date('Y'),
            'Block fixture/x is cached (#[Cache]), but its template calls wp_human_time_diff(), which depends on the current time without a second timestamp; it cannot be used in cached blocks.' => static fn(): string => $functions->humanTimeDiff(0),
        ];
        foreach ($failures as $message => $call) {
            try {
                $inBlock(['url'], $call);
                self::fail('LogicException expected: ' . $message);
            } catch (LogicException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testACachedRouteKeepsStatusAndHeadersOfAResponseBelow400(): void
    {
        $routes = array_column($this->manifest['routes'], null, 'method');
        $registrar = new RestRegistrar(new Container($this->manifest['services']), new ValueFactory($this->manifest['values']), false, $this->cache);

        $first = self::respond($registrar, $routes['detail']);
        $hit = self::respond($registrar, $routes['detail']);
        self::assertInstanceOf(WP_REST_Response::class, $hit);
        self::assertSame([['id' => 1, 'count' => 1, 'method' => 'GET'], 203, ['X-Count' => '1']], [$hit->get_data(), $hit->get_status(), $hit->get_headers()]);
        self::assertInstanceOf(WP_REST_Response::class, $first);
        self::assertSame($first->get_data(), $hit->get_data());

        $missing = self::respond($registrar, $routes['detail'], ['id' => 0]);
        self::assertInstanceOf(WP_REST_Response::class, $missing);
        self::assertSame([404, ['X-Count' => '2']], [$missing->get_status(), $missing->get_headers()]);
        $again = self::respond($registrar, $routes['detail'], ['id' => 0]);
        self::assertInstanceOf(WP_REST_Response::class, $again);
        self::assertSame(['X-Count' => '3'], $again->get_headers(), 'A response with status 404 is not stored.');
    }

    public function testACachedRouteRespondsOncePerArgumentsAndVariant(): void
    {
        $route = array_column($this->manifest['routes'], null, 'method')['index'];
        $registrar = new RestRegistrar(new Container($this->manifest['services']), new ValueFactory($this->manifest['values']), false, $this->cache);

        self::assertSame(['limit' => 5, 'count' => 1], self::respond($registrar, $route));
        self::assertSame(['limit' => 5, 'count' => 1], self::respond($registrar, $route));
        self::assertSame(['limit' => 6, 'count' => 2], self::respond($registrar, $route, ['limit' => 6]));
        self::assertSame(['limit' => 5, 'count' => 3], self::respond($registrar, $route, ['limit' => 5]), 'A passed argument differs from its default.');
        $GLOBALS['wp_app_framework_test_user'] = new WP_User(3);
        self::assertSame(['limit' => 5, 'count' => 4], self::respond($registrar, $route));

        $stored = count(Recorded::transients());
        self::assertInstanceOf(WP_Error::class, self::respond($registrar, $route, ['limit' => 0]));
        self::assertInstanceOf(WP_Error::class, self::respond($registrar, $route, ['limit' => 0]));
        self::assertCount($stored, Recorded::transients(), 'Errors are not stored.');
    }
}
