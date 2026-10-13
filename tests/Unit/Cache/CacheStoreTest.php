<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Cache;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\Cache\Backend\FileBackend;
use Saij\WpAppFramework\Cache\Backend\ObjectBackend;
use Saij\WpAppFramework\Cache\Backend\TransientBackend;
use Saij\WpAppFramework\Cache\CacheStore;
use Saij\WpAppFramework\Cache\CodeGeneration;
use Saij\WpAppFramework\Cache\Tag\PostTypeTag;
use Saij\WpAppFramework\Cache\Tag\SettingsTag;
use Saij\WpAppFramework\Cache\Tag\TaxonomyTag;
use Saij\WpAppFramework\Cache\TagHooks;
use Saij\WpAppFramework\Cache\TagNames;
use Saij\WpAppFramework\Cache\TagVersions;
use Saij\WpAppFramework\Cache\Vary\CookieVary;
use Saij\WpAppFramework\Cache\Vary\HeaderVary;
use Saij\WpAppFramework\Cache\Vary\LoggedInVary;
use Saij\WpAppFramework\Cache\Vary\QueryParamVary;
use Saij\WpAppFramework\Cache\Vary\RoleVary;
use Saij\WpAppFramework\Cache\Vary\ServiceVary;
use Saij\WpAppFramework\Cache\Vary\UrlVary;
use Saij\WpAppFramework\Cache\Vary\UserVary;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Tests\Fixtures\CacheBroken\NotAProvider;
use Saij\WpAppFramework\Tests\Fixtures\CacheValid\Feed;
use Saij\WpAppFramework\Tests\Fixtures\CacheValid\Region;
use Saij\WpAppFramework\Tests\Fixtures\CacheValid\SiteSettings;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Support\Hooks;
use Saij\WpAppFramework\Tests\Support\Recorded;
use stdClass;
use WP_Post;
use WP_Term;
use WP_User;

/**
 * The cache store with its backends, tags and variants.
 */
final class CacheStoreTest extends TestCase
{
    private const string FILTER = 'wp_app_framework/cache/backends/my-theme';

    private string $directory;

    private TagVersions $versions;

    private int $computed = 0;

    /** @var array<mixed> */
    private array $server;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/wpaf-cache-' . bin2hex(random_bytes(4));
        $this->versions = new TagVersions('my_theme_');
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        Hooks::reset();
        if (is_dir($this->directory)) {
            Files::remove($this->directory);
        }
        $_GET = [];
        $_COOKIE = [];
        $_SERVER = $this->server;
        unset(
            $GLOBALS['wp_app_framework_test_options'],
            $GLOBALS['wp_app_framework_test_autoload'],
            $GLOBALS['wp_app_framework_test_transients'],
            $GLOBALS['wp_app_framework_test_object_cache'],
            $GLOBALS['wp_app_framework_test_object_cache_enabled'],
            $GLOBALS['wp_app_framework_test_user'],
            $GLOBALS['wp_app_framework_test_logged_in'],
            $GLOBALS['wp_app_framework_test_region'],
            $GLOBALS['wp_app_framework_test_posts'],
            $GLOBALS['wp_app_framework_test_terms'],
        );
    }

    private function store(string $generation = 'g1'): CacheStore
    {
        return new CacheStore(
            'my-theme',
            ['object' => new ObjectBackend('my-theme'), 'file' => new FileBackend($this->directory), 'transient' => new TransientBackend('my_theme_')],
            $this->versions,
            new CodeGeneration(static fn(): string => $generation),
            new Container([Region::class => []]),
            new TagNames([SiteSettings::class => 'site']),
        );
    }

    /**
     * @phpstan-impure Computes or reads the cache.
     * @param list<\Saij\WpAppFramework\Cache\CacheTag>  $tags
     * @param list<\Saij\WpAppFramework\Cache\CacheVary> $vary
     */
    private function remember(CacheStore $store, array $tags = [], array $vary = []): int
    {
        return $store->remember('count', fn(): int => ++$this->computed, 60, $tags, $vary);
    }

    /**
     * @param list<string> $backends
     */
    private static function useBackends(array $backends): void
    {
        add_filter(self::FILTER, static fn(): array => $backends);
    }

    public function testRemembersAValueAndStoresItWithItsLifetime(): void
    {
        self::useBackends(['transient']);
        $store = $this->store();

        self::assertSame(1, $this->remember($store));
        self::assertSame(1, $this->remember($store));
        self::assertSame(1, $this->remember($this->store()), 'Later requests read the stored value.');

        $transients = Recorded::transients();
        self::assertCount(1, $transients);
        $name = (string) array_key_first($transients);
        self::assertMatchesRegularExpression('/^my_theme_cache_[0-9a-f]{32}$/', $name);
        self::assertSame(['value' => ['value' => 1], 'expiration' => 60], $transients[$name]);
    }

    public function testTheFirstAvailableBackendStoresTheEntries(): void
    {
        self::assertSame('file', $this->store()->getBackendName(), 'Without an object cache, files come next.');
        self::assertSame(1, $this->remember($this->store()));
        self::assertCount(1, array_filter(Files::below($this->directory . '/1'), static fn(\SplFileInfo $file): bool => $file->isFile()));

        $GLOBALS['wp_app_framework_test_object_cache_enabled'] = true;
        $store = $this->store();
        self::assertSame('object', $store->getBackendName());
        self::assertSame(2, $this->remember($store));
        $objects = Recorded::objectCache(ObjectBackend::GROUP);
        $key = (string) array_key_first($objects);
        self::assertMatchesRegularExpression('/^my-theme:[0-9a-f]{32}$/', $key);
        self::assertSame(['data' => ['value' => 2], 'expire' => 60], $objects[$key]);
    }

    public function testAnEmptyListTurnsCachingOff(): void
    {
        self::useBackends([]);
        $store = $this->store();

        self::assertNull($store->getBackendName());
        self::assertSame(1, $this->remember($store));
        self::assertSame(2, $this->remember($store));
    }

    public function testAnUnknownBackendIsAnError(): void
    {
        self::useBackends(['object', 'redis']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("The filter wp_app_framework/cache/backends/my-theme returned the unknown backend 'redis'; use \"object\", \"file\", \"transient\".");
        $this->store()->getBackendName();
    }

    public function testEveryVariantKeepsEntriesOfItsOwn(): void
    {
        self::useBackends(['transient']);
        $_SERVER['REQUEST_URI'] = '/news/?page=1';
        $count = $this->countVariants(...);

        self::assertSame(1, $count());
        self::assertSame(1, $count());

        $changes = [
            static function (): void {
                $_SERVER['REQUEST_URI'] = '/archive/?page=1';
            },
            static function (): void {
                $_GET['page'] = '';
            },
            static function (): void {
                $_GET['page'] = '2';
            },
            static function (): void {
                $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de';
            },
            static function (): void {
                $_COOKIE['region'] = 'eu';
            },
            static function (): void {
                $GLOBALS['wp_app_framework_test_region'] = 'north';
            },
            static function (): void {
                $GLOBALS['wp_app_framework_test_logged_in'] = true;
            },
            static function (): void {
                $GLOBALS['wp_app_framework_test_user'] = new WP_User(5);
            },
            static function (): void {
                $GLOBALS['wp_app_framework_test_user'] = new WP_User((object) ['ID' => 5, 'roles' => ['editor']]);
            },
        ];
        foreach ($changes as $index => $change) {
            $change();
            self::assertSame($index + 2, $count(), sprintf('Change %d creates a new entry.', $index + 1));
            self::assertSame($index + 2, $count(), sprintf('Change %d is read from the cache.', $index + 1));
        }

        // The query string is no part of UrlVary; the parameter "page" is (unchanged).
        $_SERVER['REQUEST_URI'] = '/news/?page=3';
        self::assertSame(11, $count());
        $_SERVER['REQUEST_URI'] = '/news/?other=1';
        self::assertSame(11, $count());
    }

    /**
     * @phpstan-impure Computes or reads the cache.
     */
    private function countVariants(): int
    {
        return $this->remember($this->store(), [], [
            new UserVary(),
            new LoggedInVary(),
            new RoleVary(),
            new UrlVary(),
            new QueryParamVary('page'),
            new HeaderVary('Accept-Language'),
            new CookieVary('region'),
            new ServiceVary(Region::class),
        ]);
    }

    /**
     * @phpstan-impure Computes or reads the cache.
     */
    private function countTagged(): int
    {
        return $this->remember($this->store(), [new PostTypeTag('post'), new TaxonomyTag('category'), new SettingsTag(SiteSettings::class), Feed::Weather]);
    }

    public function testTagsInvalidateTheirEntries(): void
    {
        self::useBackends(['transient']);
        new TagHooks($this->versions, ['my_theme_site' => 'site'])->register();
        $post = new WP_Post((object) ['ID' => 7, 'post_type' => 'post']);
        $page = new WP_Post((object) ['ID' => 8, 'post_type' => 'page']);
        $GLOBALS['wp_app_framework_test_posts'] = [7 => $post, 8 => $page];
        $GLOBALS['wp_app_framework_test_terms'] = [3 => new WP_Term((object) ['term_id' => 3, 'taxonomy' => 'category'])];
        $count = $this->countTagged(...);

        self::assertSame(1, $count());
        $this->store()->invalidate(Feed::Weather);
        self::assertSame(2, $count());

        do_action('clean_post_cache', 8, $page);
        do_action('updated_post_meta', 1, 7, '_edit_lock', '1:1');
        self::assertSame(2, $count(), 'Pages and the edit lock leave posts alone.');

        $invalidations = [
            static fn() => do_action('clean_post_cache', 7, $post),
            static fn() => do_action('added_post_meta', 1, 7, 'subtitle', 'x'),
            static fn() => do_action('updated_post_meta', 1, 7, 'subtitle', 'y'),
            static fn() => do_action('deleted_post_meta', [1], 7, 'subtitle', 'y'),
            static fn() => do_action('clean_term_cache', [3], 'category', true),
            static fn() => do_action('set_object_terms', 7, [3], [3], 'category', false, []),
            static fn() => do_action('delete_term_relationships', 7, [3], 'category'),
            static fn() => do_action('updated_term_meta', 1, 3, 'color', 'red'),
            static fn() => update_option('my_theme_site', ['tagline' => 'New']),
            static fn() => update_option('my_theme_site', ['tagline' => 'Newer']),
        ];
        foreach ($invalidations as $index => $invalidate) {
            $invalidate();
            self::assertSame($index + 3, $count(), sprintf('Invalidation %d', $index + 1));
            self::assertSame($index + 3, $count());
        }

        do_action('clean_term_cache', [4], 'post_tag', true);
        self::assertSame(12, $count(), 'Other taxonomies leave the entry alone.');
    }

    public function testTagVersionsAreAutoloadedOptionsCreatedOnFirstRead(): void
    {
        self::useBackends(['transient']);
        $option = $this->versions->option('custom:' . Feed::class . ':weather');

        $this->store()->invalidate(Feed::Weather);
        self::assertArrayNotHasKey($option, Recorded::options(), 'Invalidating a tag nobody read creates nothing.');

        $this->remember($this->store(), [Feed::Weather]);
        self::assertMatchesRegularExpression('/^my_theme_cache_tag_[0-9a-f]{32}$/', $option);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', (string) (is_string($version = Recorded::options()[$option] ?? null) ? $version : ''));
        $autoload = $GLOBALS['wp_app_framework_test_autoload'] ?? [];
        self::assertTrue(is_array($autoload) && ($autoload[$option] ?? null) === true);
    }

    public function testNewCodeReadsNoEntriesOfOldCode(): void
    {
        self::useBackends(['transient']);

        self::assertSame(1, $this->remember($this->store('g1')));
        self::assertSame(2, $this->remember($this->store('g2')));
        self::assertSame(1, $this->remember($this->store('g1')));
    }

    public function testValuesMustBePlainData(): void
    {
        self::useBackends(['transient']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("The cache value of key items contains stdClass at ['list'][1]; cache values must be plain data (null, booleans, numbers, strings and arrays of them).");
        $this->store()->remember('items', static fn(): array => ['list' => [1, new stdClass()]], 60);
    }

    private function rememberFailing(): void
    {
        $this->store()->remember('failing', static function (): int {
            throw new RuntimeException('no data');
        }, 60);
    }

    public function testExceptionsOfTheComputationAreNotStored(): void
    {
        self::useBackends(['transient']);
        try {
            $this->rememberFailing();
            self::fail('RuntimeException expected.');
        } catch (RuntimeException $exception) {
            self::assertSame('no data', $exception->getMessage());
        }

        self::assertSame([], Recorded::transients());
    }

    public function testInvalidArgumentsOfRememberAndInvalidate(): void
    {
        $store = $this->store();
        $failures = [
            'CacheStore::remember() needs a key that is not empty.' => static fn() => $store->remember('', static fn(): int => 1, 60),
            'CacheStore::remember("x"): the ttl must be at least 1 second, 0 given; the tag post_type:post is listed twice.' => static fn() => $store->remember('x', static fn(): int => 1, 0, [new PostTypeTag('post'), new PostTypeTag('post')]),
            'CacheStore::remember("x"): ServiceVary(' . NotAProvider::class . '::class): ' . NotAProvider::class . ' does not implement VaryProvider.' => static fn() => $store->remember('x', static fn(): int => 1, 60, [], [
                // @phpstan-ignore argument.type (checked at run time)
                new ServiceVary(NotAProvider::class),
            ]),
            'CacheStore::invalidate(): SettingsTag needs a class with #[SettingsGroup] of this theme or plugin, ' . Region::class . ' given.' => static fn() => $store->invalidate(new SettingsTag(Region::class)),
        ];
        foreach ($failures as $message => $call) {
            try {
                $call();
                self::fail('InvalidArgumentException expected: ' . $message);
            } catch (InvalidArgumentException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testExpiredFilesAreMissesAndCleanedUp(): void
    {
        $backend = new FileBackend($this->directory);
        self::assertTrue($backend->isAvailable());
        $backend->set('aa11', 'fresh', 60);
        $backend->set('bb22', 'stale', 60);
        $stale = $this->directory . '/1/bb/bb22.php';
        file_put_contents($stale, "<?php\n\nreturn " . var_export(['expires' => time() - 1, 'value' => 'stale'], true) . ";\n");
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($stale, true);
        }
        $leftover = $this->directory . '/1/aa/aa11.phpXYZ';
        touch($leftover, time() - 120);

        self::assertSame(['value' => 'fresh'], $backend->get('aa11'));
        self::assertNull($backend->get('bb22'));
        self::assertNull($backend->get('cc33'));

        $backend->cleanUp();
        self::assertFileExists($this->directory . '/1/aa/aa11.php');
        self::assertFileDoesNotExist($stale);
        self::assertFileDoesNotExist($leftover);
    }

    public function testFilesAreUnavailableWithoutAWritableDirectory(): void
    {
        mkdir($this->directory);
        touch($this->directory . '/file');

        self::assertFalse(@new FileBackend($this->directory . '/file/data')->isAvailable());
    }

    public function testCodeGenerationOfFilesAndCodeVersion(): void
    {
        mkdir($this->directory . '/src', 0777, true);
        file_put_contents($this->directory . '/src/A.php', '<?php // a');
        file_put_contents($this->directory . '/src/a.twig', 'a');
        $files = CodeGeneration::ofFiles([$this->directory . '/src', $this->directory . '/missing']);
        $code = CodeGeneration::codeVersion([$this->directory . '/src']);

        touch($this->directory . '/src/a.twig', time() + 10);
        clearstatcache();
        self::assertNotSame($files, CodeGeneration::ofFiles([$this->directory . '/src', $this->directory . '/missing']), 'A changed template changes the development generation.');
        self::assertSame($code, CodeGeneration::codeVersion([$this->directory . '/src']), 'The code version depends on PHP contents only.');

        file_put_contents($this->directory . '/src/A.php', '<?php // b');
        self::assertNotSame($code, CodeGeneration::codeVersion([$this->directory . '/src']));
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $code);
    }
}
