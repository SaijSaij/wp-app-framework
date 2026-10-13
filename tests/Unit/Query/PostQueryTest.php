<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Query;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use NoDiscard;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Saij\WpAppFramework\Cache\Backend\FileBackend;
use Saij\WpAppFramework\Cache\CacheStore;
use Saij\WpAppFramework\Cache\CodeGeneration;
use Saij\WpAppFramework\Cache\Tag\PostTypeTag;
use Saij\WpAppFramework\Cache\Tag\TaxonomyTag;
use Saij\WpAppFramework\Cache\TagNames;
use Saij\WpAppFramework\Cache\TagVersions;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Post\Category;
use Saij\WpAppFramework\Post\Page;
use Saij\WpAppFramework\Post\Post;
use Saij\WpAppFramework\Post\PostFormat;
use Saij\WpAppFramework\Post\PostStatus;
use Saij\WpAppFramework\Query\All;
use Saij\WpAppFramework\Query\Any;
use Saij\WpAppFramework\Query\Comparison;
use Saij\WpAppFramework\Query\Condition;
use Saij\WpAppFramework\Query\Direction;
use Saij\WpAppFramework\Query\DiscussionStatus;
use Saij\WpAppFramework\Query\Field\StringField;
use Saij\WpAppFramework\Query\MetaOrderClauses;
use Saij\WpAppFramework\Query\PostOrder;
use Saij\WpAppFramework\Query\PostQuery;
use Saij\WpAppFramework\Query\SearchColumn;
use Saij\WpAppFramework\Query\Terms;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\Density;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\Event;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\EventDetails;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\EventDetailsFields;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\ExtrasFields;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\Genre;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\Venue;
use Saij\WpAppFramework\Tests\Support\Files;
use Saij\WpAppFramework\Tests\Support\Hooks;
use Saij\WpAppFramework\Tests\Support\Posts;
use Saij\WpAppFramework\Tests\Support\Recorded;
use WP_Term;

/**
 * Queries: the arguments of WP_Query, running them, loading the post objects and caching the result.
 */
final class PostQueryTest extends TestCase
{
    private string $directory;

    private ?CacheStore $cache = null;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/wpaf-query-' . bin2hex(random_bytes(4));
        Posts::store(cache: fn(): CacheStore => $this->cache ??= new CacheStore(
            'my-theme',
            ['file' => new FileBackend($this->directory)],
            new TagVersions('my_theme_'),
            new CodeGeneration(static fn(): string => 'g1'),
            new Container([]),
            new TagNames([], [Event::class => 'fixture_event'], [Genre::class => 'fixture_genre', Category::class => 'category']),
        ));
    }

    protected function tearDown(): void
    {
        Posts::reset();
        Hooks::reset();
        if (is_dir($this->directory)) {
            Files::remove($this->directory);
        }
        unset($GLOBALS['wp_app_framework_test_options'], $GLOBALS['wp_app_framework_test_autoload']);
    }

    public function testPostDataBecomesArgumentsOfWpQuery(): void
    {
        $berlin = new DateTimeZone('Europe/Berlin');
        $venue = Venue::fromWpPost(Posts::add(20, 'fixture_venue'));

        $args = Venue::query()
            ->status(PostStatus::Publish, PostStatus::Draft, PostStatus::Publish)
            ->ids(5, $venue)
            ->exclude(7)
            ->exclude(8)
            ->author(3, 4)
            ->parent($venue)
            ->search('jazz')
            ->publishedAfter(new DateTimeImmutable('2026-10-01 20:00', $berlin))
            ->publishedBefore(new DateTimeImmutable('2026-11-01 00:00:00', $berlin))
            ->toArgs();

        self::assertSame([
            'post_type' => 'fixture_venue',
            'post_status' => ['publish', 'draft'],
            'fields' => 'ids',
            'ignore_sticky_posts' => true,
            'suppress_filters' => false,
            'author__in' => [3, 4],
            'post_parent' => 20,
            's' => 'jazz',
            'date_query' => [['column' => 'post_date_gmt', 'after' => '2026-10-01 18:00:00', 'before' => '2026-10-31 23:00:00', 'inclusive' => false]],
            // 7 and 8 are excluded from the IDs: WordPress ignores post__not_in next to post__in.
            'post__in' => [5, 20],
        ], $args);
        self::assertSame(0, Venue::query()->parent(null)->toArgs()['post_parent']);
    }

    public function testQueriesNeverChangeAndWarnWhenAResultIsDiscarded(): void
    {
        $base = Event::query()->where(EventDetailsFields::capacity()->greaterThan(10));
        $limited = $base->limit(3)->orderBy(PostOrder::Title);

        self::assertNotSame($base, $limited);
        self::assertArrayNotHasKey('orderby', $base->toArgs());
        self::assertSame(['title' => 'ASC'], $limited->toArgs()['orderby']);

        // Every method that returns a changed query is marked, so PHP and PHPStan report a lost result.
        foreach (new ReflectionClass(PostQuery::class)->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();
            if ($type instanceof ReflectionNamedType && $type->getName() === 'static') {
                self::assertNotSame([], $method->getAttributes(NoDiscard::class), $method->getName());
            }
        }
    }

    public function testConditionsOnMetaFieldsIncludePostsWithoutValueIfTheDefaultMatches(): void
    {
        Posts::add(20, 'fixture_venue');
        $args = Event::query()->where(
            EventDetailsFields::capacity()->greaterThan(10),
            EventDetailsFields::capacity()->lessThan(5),
            EventDetailsFields::density()->equals(Density::Normal),
            EventDetailsFields::venue()->isNull(),
            EventDetailsFields::venue()->in(Venue::get(20), 21),
            EventDetailsFields::startDate()->contains('2026'),
            EventDetailsFields::startDate()->between('2026-01-01', '2026-12-31'),
        )->toArgs();

        $missing = static fn(string $key): array => ['key' => $key, 'compare' => 'NOT EXISTS'];
        self::assertSame([
            'relation' => 'AND',
            ['key' => 'fixture_capacity', 'compare' => '>', 'value' => 10, 'type' => 'SIGNED'],
            // The default 0 is less than 5.
            ['relation' => 'OR', ['key' => 'fixture_capacity', 'compare' => '<', 'value' => 5, 'type' => 'SIGNED'], $missing('fixture_capacity')],
            ['relation' => 'OR', ['key' => 'fixture_density', 'compare' => '=', 'value' => 'normal', 'type' => 'CHAR'], $missing('fixture_density')],
            ['relation' => 'OR', ['key' => 'fixture_venue', 'compare' => '=', 'value' => 0, 'type' => 'SIGNED'], $missing('fixture_venue')],
            ['key' => 'fixture_venue', 'compare' => 'IN', 'value' => [20, 21], 'type' => 'SIGNED'],
            ['key' => 'fixture_start_date', 'compare' => 'LIKE', 'value' => '2026', 'type' => 'CHAR'],
            ['key' => 'fixture_start_date', 'compare' => 'BETWEEN', 'value' => ['2026-01-01', '2026-12-31'], 'type' => 'CHAR'],
        ], $args['meta_query']);

        // A bool is stored as "1" or "": false is the default.
        self::assertSame(
            ['relation' => 'AND', ['relation' => 'OR', ['key' => 'fixture_featured', 'compare' => '=', 'value' => 0, 'type' => 'SIGNED'], $missing('fixture_featured')]],
            Post::query()->where(ExtrasFields::featured()->equals(false))->toArgs()['meta_query'],
        );
    }

    public function testGroupsAndTermsBecomeNestedClauses(): void
    {
        $jazz = new WP_Term((object) ['term_id' => 4, 'taxonomy' => 'fixture_genre']);
        $args = Event::query()->where(
            Any::of(
                EventDetailsFields::capacity()->equals(3),
                All::of(EventDetailsFields::capacity()->greaterOrEqual(100), EventDetailsFields::density()->notEquals(Density::Compact)),
            ),
            Terms::in(Genre::class, [$jazz, 5]),
            Any::of(Terms::notIn(Genre::class, [6], includeChildren: false), Terms::notExists(Genre::class)),
        )->toArgs();

        self::assertSame([
            'relation' => 'AND',
            [
                'relation' => 'OR',
                ['key' => 'fixture_capacity', 'compare' => '=', 'value' => 3, 'type' => 'SIGNED'],
                [
                    'relation' => 'AND',
                    ['key' => 'fixture_capacity', 'compare' => '>=', 'value' => 100, 'type' => 'SIGNED'],
                    ['relation' => 'OR', ['key' => 'fixture_density', 'compare' => '!=', 'value' => 'compact', 'type' => 'CHAR'], ['key' => 'fixture_density', 'compare' => 'NOT EXISTS']],
                ],
            ],
        ], $args['meta_query']);
        self::assertSame([
            'relation' => 'AND',
            ['taxonomy' => 'fixture_genre', 'operator' => 'IN', 'include_children' => true, 'field' => 'term_id', 'terms' => [4, 5]],
            [
                'relation' => 'OR',
                ['taxonomy' => 'fixture_genre', 'operator' => 'NOT IN', 'include_children' => false, 'field' => 'term_id', 'terms' => [6]],
                ['taxonomy' => 'fixture_genre', 'operator' => 'NOT EXISTS', 'include_children' => true],
            ],
        ], $args['tax_query']);
    }

    public function testAGroupCannotMixMetaFieldsAndTerms(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(Any::class . '::of() mixes conditions on meta fields and on terms; WordPress joins the two with AND, so a group holds conditions of one kind.');

        Any::of(EventDetailsFields::capacity()->equals(3), Terms::exists(Genre::class));
    }

    public function testConditionsOfOtherClassesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is no condition of the framework; conditions come from meta fields, Terms, Any and All.');

        $condition = new class implements Condition {};
        (void) Event::query()->where($condition);
    }

    public function testATermOfAnotherTaxonomyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Terms of %s: the term 4 belongs to the taxonomy category, not to fixture_genre.', Genre::class));

        Event::query()->where(Terms::in(Genre::class, [new WP_Term((object) ['term_id' => 4, 'taxonomy' => 'category'])]))->toArgs();
    }

    public function testMetaFieldsMustBelongToThePostClassAndFitTheField(): void
    {
        try {
            Post::query()->where(EventDetailsFields::capacity()->equals(1))->toArgs();
            self::fail('A meta class of another post class.');
        } catch (LogicException $exception) {
            self::assertStringStartsWith(sprintf('%s is no meta class of %s;', EventDetails::class, Post::class), $exception->getMessage());
        }

        try {
            Event::query()->where(new StringField(EventDetails::class, 'capacity')->equals('1'))->toArgs();
            self::fail('A field class of another type.');
        } catch (LogicException $exception) {
            self::assertSame(
                sprintf('%s::$capacity needs %s, %s given; check the class with #[MetaFields] (rebuild the manifest after code changes).', EventDetails::class, \Saij\WpAppFramework\Query\Field\IntField::class, StringField::class),
                $exception->getMessage(),
            );
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('%s has no field $capacityy (fields: capacity, startDate, venue, tickets, density, note, photo, alternatives, related); check the class with #[MetaFields].', EventDetails::class));
        Event::query()->where(new StringField(EventDetails::class, 'capacityy')->equals('1'))->toArgs();
    }

    public function testListsAndValueClassesCannotBeQueried(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('%s::$tickets is a list or value class, stored serialized; only fields with one int, float, string, bool, enum, ID or post can be queried', EventDetails::class));

        Event::query()->where(new StringField(EventDetails::class, 'tickets')->equals('x'))->toArgs();
    }

    public function testSortingByPostDataIsLeftToWordPress(): void
    {
        $args = Event::query()->orderBy(PostOrder::Title)->orderBy(PostOrder::Date, Direction::Desc)->toArgs();

        self::assertSame(['title' => 'ASC', 'date' => 'DESC'], $args['orderby']);
        self::assertArrayNotHasKey(MetaOrderClauses::QUERY_VAR, $args);
    }

    public function testSortingByMetaFieldsWritesTheClausesOfThisQueryOnly(): void
    {
        $GLOBALS['wp_app_framework_test_query_result'] = ['posts' => []];
        $query = Event::query()
            ->orderBy(PostOrder::Title)
            ->orderBy(EventDetailsFields::capacity(), Direction::Desc)
            ->orderBy(EventDetailsFields::density());

        $args = $query->toArgs();
        self::assertSame('none', $args['orderby']);
        self::assertSame([
            ['column' => 'post_title', 'direction' => 'ASC'],
            ['meta' => 'fixture_capacity', 'default' => '0', 'cast' => 'SIGNED', 'direction' => 'DESC'],
            ['meta' => 'fixture_density', 'default' => 'normal', 'cast' => 'CHAR', 'direction' => 'ASC'],
        ], $args[MetaOrderClauses::QUERY_VAR]);

        self::assertSame([], $query->get());
        $clauses = Recorded::queryClauses()[0];
        self::assertSame(
            " LEFT JOIN wp_postmeta AS wpaf_order_1 ON (wpaf_order_1.post_id = wp_posts.ID AND wpaf_order_1.meta_key = 'fixture_capacity')"
            . " LEFT JOIN wp_postmeta AS wpaf_order_2 ON (wpaf_order_2.post_id = wp_posts.ID AND wpaf_order_2.meta_key = 'fixture_density')",
            $clauses['join'],
        );
        self::assertSame('wp_posts.ID', $clauses['groupby']);
        self::assertSame(
            "wp_posts.post_title ASC, COALESCE(MIN(CAST(wpaf_order_1.meta_value AS SIGNED)), CAST('0' AS SIGNED)) DESC, COALESCE(MIN(CAST(wpaf_order_2.meta_value AS CHAR)), CAST('normal' AS CHAR)) ASC",
            $clauses['orderby'],
        );
        // The filter is gone after the query: other queries keep their clauses.
        self::assertSame([], Recorded::filters('posts_clauses'));
    }

    public function testGetLoadsThePostObjectsInTheOrderOfTheResult(): void
    {
        Posts::add(10, 'fixture_event');
        Posts::add(12, 'fixture_event');
        Posts::add(13, 'post');
        $GLOBALS['wp_app_framework_test_query_result'] = ['posts' => [12, 10, 13, 99]];

        $events = Event::query()->limit(5)->offset(2)->get();

        self::assertSame([12, 10], array_map(static fn(Event $event): ?int => $event->getId(), $events));
        self::assertSame([[12, 10, 13, 99]], Recorded::primed());
        $args = Recorded::queries()[0];
        self::assertSame([5, 2, true], [$args['posts_per_page'], $args['offset'], $args['no_found_rows']]);

        Event::query()->get();
        self::assertSame([-1, true], [Recorded::queries()[1]['posts_per_page'], Recorded::queries()[1]['no_found_rows']]);
        self::assertArrayNotHasKey('offset', Recorded::queries()[1]);
    }

    public function testFirstCountAndPaginate(): void
    {
        Posts::add(10, 'fixture_event');
        Posts::add(11, 'fixture_event');
        $GLOBALS['wp_app_framework_test_query_result'] = ['posts' => [10, 11], 'found_posts' => 23];

        self::assertSame(10, Event::query()->first()?->getId());
        self::assertSame(1, Recorded::queries()[0]['posts_per_page']);

        self::assertSame(23, Event::query()->orderBy(EventDetailsFields::capacity())->limit(3)->count());
        $count = Recorded::queries()[1];
        self::assertSame([1, false], [$count['posts_per_page'], $count['no_found_rows']]);
        self::assertArrayNotHasKey('orderby', $count, 'count() does not sort.');

        $page = Event::query()->paginate(perPage: 2, page: 3);
        self::assertSame([[10, 11], 23, 3, 2, 12], [array_map(static fn(Event $event): ?int => $event->getId(), $page->getPosts()), $page->getTotal(), $page->getPage(), $page->getPerPage(), $page->getPageCount()]);
        self::assertSame([2, 3, false], [Recorded::queries()[2]['posts_per_page'], Recorded::queries()[2]['paged'], Recorded::queries()[2]['no_found_rows']]);

        $GLOBALS['wp_app_framework_test_query_result'] = ['posts' => []];
        self::assertNull(Event::query()->first());
    }

    public function testStandardFieldsOfPostsAndPagesBecomeArgumentsOfWpQuery(): void
    {
        $args = Venue::query()
            ->slug('hall', '', 'club')
            ->title('Main hall')
            ->parentIn(5, 6)
            ->parentNotIn(7)
            ->search('jazz club', columns: [SearchColumn::Title, SearchColumn::Excerpt, SearchColumn::Title], sentence: true)
            ->publishedAfter(new DateTimeImmutable('2026-01-01 00:00', new DateTimeZone('UTC')))
            ->modifiedBefore(new DateTimeImmutable('2026-10-01 12:00', new DateTimeZone('UTC')))
            ->hasPassword(false)
            ->menuOrder(2)
            ->commentCount(3, Comparison::GreaterOrEqual)
            ->commentStatus(DiscussionStatus::Open)
            ->pingStatus(DiscussionStatus::Closed)
            ->toArgs();

        self::assertSame('jazz club', $args['s']);
        self::assertSame([
            ['column' => 'post_date_gmt', 'after' => '2026-01-01 00:00:00', 'inclusive' => false],
            ['column' => 'post_modified_gmt', 'before' => '2026-10-01 12:00:00', 'inclusive' => false],
        ], $args['date_query']);
        foreach ([
            'post_name__in' => ['hall', 'club'],
            'title' => 'Main hall',
            'post_parent__in' => [5, 6],
            'post_parent__not_in' => [7],
            'search_columns' => ['post_title', 'post_excerpt'],
            'sentence' => true,
            'has_password' => false,
            'menu_order' => 2,
            'comment_count' => ['value' => 3, 'compare' => '>='],
            'comment_status' => 'open',
            'ping_status' => 'closed',
        ] as $name => $value) {
            self::assertSame($value, $args[$name], $name);
        }
        self::assertArrayNotHasKey('exact', $args);
        // A new search replaces the options of the previous one.
        self::assertArrayNotHasKey('search_columns', Venue::query()->search('a', [SearchColumn::Title])->search('b', exact: true)->toArgs());
    }

    public function testTemplatesAndFormatsBecomeClausesNextToTheConditions(): void
    {
        $args = Post::query()
            ->where(ExtrasFields::featured()->equals(true), Terms::in(Category::class, [3]))
            ->template('page-wide', 'default', 'landing')
            ->format(PostFormat::Video, PostFormat::Standard, PostFormat::Video)
            ->toArgs();

        self::assertSame([
            'relation' => 'AND',
            ['key' => '_wp_page_template', 'compare' => 'IN', 'value' => ['page-wide', 'landing']],
            ['key' => 'fixture_featured', 'compare' => '=', 'value' => 1, 'type' => 'SIGNED'],
        ], $args['meta_query']);
        self::assertSame([
            'relation' => 'AND',
            ['relation' => 'OR', ['taxonomy' => 'post_format', 'field' => 'slug', 'terms' => ['post-format-video'], 'operator' => 'IN'], ['taxonomy' => 'post_format', 'operator' => 'NOT EXISTS']],
            ['taxonomy' => 'category', 'operator' => 'IN', 'include_children' => true, 'field' => 'term_id', 'terms' => [3]],
        ], $args['tax_query']);

        self::assertSame(
            ['relation' => 'AND', ['relation' => 'OR', ['key' => '_wp_page_template', 'compare' => 'NOT EXISTS'], ['key' => '_wp_page_template', 'compare' => 'IN', 'value' => ['', 'default']]]],
            Page::query()->defaultTemplate()->toArgs()['meta_query'],
        );
        self::assertSame(
            ['relation' => 'AND', ['taxonomy' => 'post_format', 'field' => 'slug', 'terms' => ['post-format-aside'], 'operator' => 'IN']],
            Post::query()->format(PostFormat::Aside)->toArgs()['tax_query'],
        );
    }

    public function testStickyPostsComeFromTheirOption(): void
    {
        $GLOBALS['wp_app_framework_test_options'] = ['sticky_posts' => [3, '5']];

        self::assertSame([3, 5], Post::query()->sticky()->toArgs()['post__in']);
        self::assertSame([5], Post::query()->ids(5, 6)->sticky()->toArgs()['post__in']);
        // No sticky post among the IDs: no post at all.
        self::assertSame([0], Post::query()->ids(6)->sticky()->toArgs()['post__in']);
        self::assertSame([9, 3, 5], Post::query()->exclude(9)->sticky(false)->toArgs()['post__not_in']);
        // WordPress ignores post__not_in next to post__in: excluded from the IDs instead.
        $args = Post::query()->ids(3, 6, 7)->exclude(7)->sticky(false)->toArgs();
        self::assertSame([6], $args['post__in']);
        self::assertArrayNotHasKey('post__not_in', $args);
        self::assertSame([0], Post::query()->ids(3)->exclude(3)->toArgs()['post__in']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('%s::query()->sticky(): only posts of the type post can be sticky.', Page::class));
        (void) Page::query()->sticky();
    }

    public function testFormatsInvalidateCachedQueries(): void
    {
        Posts::add(10, 'post');
        $GLOBALS['wp_app_framework_test_query_result'] = ['posts' => [10]];
        $query = Post::query()->format(PostFormat::Video)->cache(60);

        self::assertCount(1, $query->get());
        $GLOBALS['wp_app_framework_test_query_result'] = ['posts' => []];
        self::assertCount(1, $query->get());
        $this->cache?->invalidate(new TaxonomyTag('post_format'));
        self::assertSame([], $query->get());
    }

    public function testIdsWithoutPostsFindNothingWithoutAQuery(): void
    {
        $query = Event::query()->ids();

        self::assertSame([[], 0, 0], [$query->get(), $query->count(), $query->paginate(10, 1)->getTotal()]);
        self::assertSame([], Recorded::queries());
    }

    public function testInvalidArgumentsAreErrors(): void
    {
        $errors = [];
        foreach ([
            static fn(): mixed => Event::query()->parent(null),
            static fn(): mixed => Event::query()->limit(0),
            static fn(): mixed => Event::query()->offset(-1),
            static fn(): mixed => Event::query()->search(' '),
            static fn(): mixed => Event::query()->offset(3)->get(),
            static fn(): mixed => Event::query()->limit(3)->paginate(10, 1),
            static fn(): mixed => Event::query()->paginate(10, 0),
            static fn(): mixed => Event::query()->ids(new Event()),
            static fn(): mixed => Terms::in(Genre::class, []),
            static fn(): mixed => EventDetailsFields::capacity()->in(),
            static fn(): mixed => EventDetailsFields::venue()->equals(new Venue()),
            static fn(): mixed => Event::query()->orderBy(PostOrder::Random)->cache(60)->get(),
            static fn(): mixed => Event::query()->slug(''),
            static fn(): mixed => Event::query()->title(' '),
            static fn(): mixed => Event::query()->parentIn(1),
            static fn(): mixed => Venue::query()->parentIn(),
            static fn(): mixed => Event::query()->template('default'),
            static fn(): mixed => Event::query()->menuOrder(-1),
            static fn(): mixed => Event::query()->commentCount(-1),
        ] as $call) {
            try {
                $call();
                $errors[] = 'no exception';
            } catch (InvalidArgumentException|LogicException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        self::assertSame([
            sprintf('%s::query()->parent(): the post type is not hierarchical.', Event::class),
            sprintf('%s::query()->limit() needs at least 1, 0 given.', Event::class),
            sprintf('%s::query()->offset() needs at least 0, -1 given.', Event::class),
            sprintf('%s::query()->search() needs a text.', Event::class),
            sprintf('%s::query()->offset() needs limit().', Event::class),
            sprintf('%s::query()->paginate() sets the limit and offset itself; remove limit() and offset().', Event::class),
            sprintf('%s::query()->paginate() needs at least 1 post per page and page 1, 10 and 0 given.', Event::class),
            sprintf('%s::query(): the %s has not been saved yet.', Event::class, Event::class),
            'Terms::in() needs at least one term.',
            sprintf('%s::$capacity: IN needs at least one value.', EventDetails::class),
            sprintf('%s::$venue: the %s has not been saved yet.', EventDetails::class, Venue::class),
            sprintf('%s::query(): a random order cannot be cached; remove cache() or PostOrder::Random.', Event::class),
            sprintf('%s::query()->slug() needs at least one slug.', Event::class),
            sprintf('%s::query()->title() needs a title.', Event::class),
            sprintf('%s::query()->parentIn(): the post type is not hierarchical.', Event::class),
            sprintf('%s::query()->parentIn() needs at least one post.', Venue::class),
            sprintf('%s::query()->template() needs at least one template slug; use defaultTemplate() for the default.', Event::class),
            sprintf('%s::query()->menuOrder() needs at least 0, -1 given.', Event::class),
            sprintf('%s::query()->commentCount() needs at least 0, -1 given.', Event::class),
        ], $errors);
    }

    public function testCachedQueriesStoreTheIdsUntilThePostTypeOrATaxonomyChanges(): void
    {
        Posts::add(10, 'fixture_event');
        $GLOBALS['wp_app_framework_test_query_result'] = ['posts' => [10], 'found_posts' => 7];
        $query = Event::query()->where(Terms::exists(Genre::class))->cache(60, [new PostTypeTag(Event::class)]);
        $queries = static fn(): int => count(Recorded::queries());

        self::assertSame([10], array_map(static fn(Event $event): ?int => $event->getId(), $query->get()));
        self::assertSame(7, $query->count());
        self::assertSame(2, $queries());
        $GLOBALS['wp_app_framework_test_query_result'] = ['posts' => [], 'found_posts' => 0];
        self::assertCount(1, $query->get(), 'From the cache.');
        self::assertSame(7, $query->count());
        self::assertSame(2, $queries());

        $this->cache?->invalidate(new TaxonomyTag('fixture_genre'));
        self::assertSame([], $query->get());
        self::assertSame(3, $queries());

        $GLOBALS['wp_app_framework_test_query_result'] = ['posts' => [10]];
        $this->cache?->invalidate(new PostTypeTag('fixture_event'));
        self::assertCount(1, $query->get());
        self::assertSame(4, $queries());
    }

    public function testInvalidCacheSettingsAreErrors(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('%s::query()->cache(): the ttl must be at least 1 second, 0 given.', Event::class));

        Event::query()->cache(0)->get();
    }
}
