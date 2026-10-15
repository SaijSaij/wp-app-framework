<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use Saij\WpAppFramework\Cache\CachePolicy;
use Saij\WpAppFramework\Cache\CacheTag;
use Saij\WpAppFramework\Cache\CacheVary;
use Saij\WpAppFramework\Cache\Tag\PostTypeTag;
use Saij\WpAppFramework\Cache\Tag\TaxonomyTag;
use Saij\WpAppFramework\Cache\TagNames;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Post\AbstractPost;
use Saij\WpAppFramework\Post\PostFormat;
use Saij\WpAppFramework\Post\PostRegistry;
use Saij\WpAppFramework\Post\PostStatus;
use Saij\WpAppFramework\Query\Field\Field;
use WP_Post;
use WP_Query;
use wpdb;

/**
 * A query for posts of a post class, started with `Event::query()`, run through WP_Query and returning post objects.
 *
 * A query never changes: every method returns a changed copy, so a query can be passed on and varied safely. Keep
 * the result – `$query->limit(3);` alone does nothing, and PHP warns about it (#[\NoDiscard]).
 *
 *     $events = Event::query()
 *         ->where(EventDetailsFields::capacity()->greaterThan(10))
 *         ->where(Terms::in(Genre::class, [$jazz]))
 *         ->orderBy(EventDetailsFields::start())
 *         ->limit(10)
 *         ->get();
 *
 * Defaults: published posts only, all matching posts (no limit), the sort order of WordPress (newest first). The
 * statuses are taken as given, without checking the capabilities of the current user, like save().
 *
 * @template T of AbstractPost
 */
#[NoService]
final readonly class PostQuery
{
    /** The meta key of the template of a post (get_page_template_slug()). */
    private const string TEMPLATE_KEY = '_wp_page_template';

    /**
     * @param class-string<T>                          $class
     * @param non-empty-string                         $type
     * @param non-empty-list<PostStatus>               $statuses
     * @param list<int>|null                           $ids
     * @param list<int>                                $exclude
     * @param list<int>                                $authors
     * @param list<Condition>                          $conditions
     * @param list<array{PostOrder|Field, Direction}>  $order
     * @param array{ttl: int, tags: list<CacheTag>, vary: list<CacheVary>}|null $cache
     * @param array<string, mixed>                     $args          Further arguments of WP_Query, as set by the methods.
     * @param list<string>|null                        $templates     Template slugs; [] for the default template.
     * @param list<PostFormat>                         $formats
     */
    private function __construct(
        private string $class,
        private string $type,
        private array $statuses = [PostStatus::Publish],
        private ?array $ids = null,
        private array $exclude = [],
        private array $authors = [],
        private ?int $parent = null,
        private ?string $search = null,
        private ?string $after = null,
        private ?string $before = null,
        private array $conditions = [],
        private array $order = [],
        private ?int $limit = null,
        private int $offset = 0,
        private ?array $cache = null,
        private array $args = [],
        private ?string $modifiedAfter = null,
        private ?string $modifiedBefore = null,
        private ?array $templates = null,
        private array $formats = [],
        private ?bool $sticky = null,
    ) {}

    /**
     * @internal Start queries with the post class: `Event::query()`.
     *
     * @template P of AbstractPost
     * @param class-string<P> $class
     * @return self<P>
     */
    public static function of(string $class): self
    {
        return new self($class, PostRegistry::postStore($class)->post($class)['name']);
    }

    /**
     * Posts that meet all conditions (meta fields, terms, Any, All); adds to the conditions of earlier calls.
     */
    #[\NoDiscard]
    public function where(Condition ...$conditions): static
    {
        foreach ($conditions as $condition) {
            if (Group::kindOf($condition) === 'row') {
                throw new InvalidArgumentException(sprintf('%s::query()->where() takes conditions on meta fields and terms; conditions on the columns of a table belong to its row query.', $this->class));
            }
        }

        return clone($this, ['conditions' => [...$this->conditions, ...array_values($conditions)]]);
    }

    /**
     * Posts with one of the statuses, instead of published posts only. The capabilities of the current user are not
     * checked.
     */
    #[\NoDiscard]
    public function status(PostStatus $status, PostStatus ...$more): static
    {
        $statuses = [];
        foreach ([$status, ...$more] as $each) {
            $statuses[$each->value] = $each;
        }

        return clone($this, ['statuses' => array_values($statuses)]);
    }

    /**
     * Only these posts; without arguments, no posts at all.
     */
    #[\NoDiscard]
    public function ids(AbstractPost|int ...$posts): static
    {
        return clone($this, ['ids' => array_map($this->id(...), array_values($posts))]);
    }

    /**
     * Not these posts.
     */
    #[\NoDiscard]
    public function exclude(AbstractPost|int ...$posts): static
    {
        return clone($this, ['exclude' => [...$this->exclude, ...array_map($this->id(...), array_values($posts))]]);
    }

    /**
     * Posts of one of the authors (user IDs).
     */
    #[\NoDiscard]
    public function author(int $author, int ...$more): static
    {
        return clone($this, ['authors' => [$author, ...array_values($more)]]);
    }

    /**
     * Children of the post (an object or its ID), or top-level posts with null (hierarchical post types).
     *
     * @param T|int|null $parent
     */
    #[\NoDiscard]
    public function parent(AbstractPost|int|null $parent): static
    {
        if (!PostRegistry::postStore($this->class)->post($this->class)['hierarchical']) {
            throw new LogicException(sprintf('%s::query()->parent(): the post type is not hierarchical.', $this->class));
        }

        return clone($this, ['parent' => $parent === null ? 0 : $this->id($parent)]);
    }

    /**
     * Children of one of the posts (hierarchical post types).
     *
     * @param T|int ...$parents
     */
    #[\NoDiscard]
    public function parentIn(AbstractPost|int ...$parents): static
    {
        return $this->withArg('post_parent__in', $this->parents('parentIn', $parents));
    }

    /**
     * Posts that are no children of the posts (hierarchical post types).
     *
     * @param T|int ...$parents
     */
    #[\NoDiscard]
    public function parentNotIn(AbstractPost|int ...$parents): static
    {
        return $this->withArg('post_parent__not_in', $this->parents('parentNotIn', $parents));
    }

    /**
     * Posts that contain the words, searched like the search of WordPress: in the title, excerpt and content, or in
     * the given columns; with $exact the text must be a whole column value, with $sentence the words are one phrase.
     *
     * @param list<SearchColumn> $columns
     */
    #[\NoDiscard]
    public function search(string $text, array $columns = [], bool $exact = false, bool $sentence = false): static
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException(sprintf('%s::query()->search() needs a text.', $this->class));
        }
        $args = $this->args;
        unset($args['search_columns'], $args['exact'], $args['sentence']);
        if ($columns !== []) {
            $args['search_columns'] = array_values(array_unique(array_map(static fn(SearchColumn $column): string => $column->value, $columns)));
        }
        if ($exact) {
            $args['exact'] = true;
        }
        if ($sentence) {
            $args['sentence'] = true;
        }

        return clone($this, ['search' => $text, 'args' => $args]);
    }

    /**
     * Posts with one of the slugs (post_name).
     */
    #[\NoDiscard]
    public function slug(string ...$slugs): static
    {
        $slugs = array_values(array_filter($slugs, static fn(string $slug): bool => $slug !== ''));
        if ($slugs === []) {
            throw new InvalidArgumentException(sprintf('%s::query()->slug() needs at least one slug.', $this->class));
        }

        return $this->withArg('post_name__in', $slugs);
    }

    /**
     * Posts with exactly this title.
     */
    #[\NoDiscard]
    public function title(string $title): static
    {
        if (trim($title) === '') {
            throw new InvalidArgumentException(sprintf('%s::query()->title() needs a title.', $this->class));
        }

        return $this->withArg('title', $title);
    }

    /**
     * Posts last changed after the time (not at the time).
     */
    #[\NoDiscard]
    public function modifiedAfter(DateTimeInterface $time): static
    {
        return clone($this, ['modifiedAfter' => self::utc($time)]);
    }

    /**
     * Posts last changed before the time (not at the time).
     */
    #[\NoDiscard]
    public function modifiedBefore(DateTimeInterface $time): static
    {
        return clone($this, ['modifiedBefore' => self::utc($time)]);
    }

    /**
     * Posts with one of the templates, by slug (`page-wide` for templates/page-wide.html).
     */
    #[\NoDiscard]
    public function template(string ...$slugs): static
    {
        $slugs = array_values(array_filter($slugs, static fn(string $slug): bool => $slug !== '' && $slug !== 'default'));
        if ($slugs === []) {
            throw new InvalidArgumentException(sprintf('%s::query()->template() needs at least one template slug; use defaultTemplate() for the default.', $this->class));
        }

        return clone($this, ['templates' => $slugs]);
    }

    /**
     * Posts with the default template of their type: no template stored.
     */
    #[\NoDiscard]
    public function defaultTemplate(): static
    {
        return clone($this, ['templates' => []]);
    }

    /**
     * Posts with one of the formats; PostFormat::Standard is a post without format.
     */
    #[\NoDiscard]
    public function format(PostFormat $format, PostFormat ...$more): static
    {
        $formats = [];
        foreach ([$format, ...$more] as $each) {
            $formats[$each->value] = $each;
        }

        return clone($this, ['formats' => array_values($formats)]);
    }

    /**
     * Sticky posts only, or with false no sticky posts (posts of WordPress only).
     */
    #[\NoDiscard]
    public function sticky(bool $sticky = true): static
    {
        if ($this->type !== 'post') {
            throw new LogicException(sprintf('%s::query()->sticky(): only posts of the type post can be sticky.', $this->class));
        }

        return clone($this, ['sticky' => $sticky]);
    }

    /**
     * Posts with a password, or with false without one.
     */
    #[\NoDiscard]
    public function hasPassword(bool $password = true): static
    {
        return $this->withArg('has_password', $password);
    }

    /**
     * Posts with this menu order (page attributes).
     */
    #[\NoDiscard]
    public function menuOrder(int $order): static
    {
        if ($order < 0) {
            throw new InvalidArgumentException(sprintf('%s::query()->menuOrder() needs at least 0, %d given.', $this->class, $order));
        }

        return $this->withArg('menu_order', $order);
    }

    /**
     * Posts whose number of approved comments compares to $count.
     */
    #[\NoDiscard]
    public function commentCount(int $count, Comparison $comparison = Comparison::Equal): static
    {
        if ($count < 0) {
            throw new InvalidArgumentException(sprintf('%s::query()->commentCount() needs at least 0, %d given.', $this->class, $count));
        }

        return $this->withArg('comment_count', ['value' => $count, 'compare' => $comparison->value]);
    }

    /**
     * Posts whose comments are open or closed.
     */
    #[\NoDiscard]
    public function commentStatus(DiscussionStatus $status): static
    {
        return $this->withArg('comment_status', $status->value);
    }

    /**
     * Posts whose pings (trackbacks, pingbacks) are open or closed.
     */
    #[\NoDiscard]
    public function pingStatus(DiscussionStatus $status): static
    {
        return $this->withArg('ping_status', $status->value);
    }

    /**
     * Posts published after the time (not at the time).
     */
    #[\NoDiscard]
    public function publishedAfter(DateTimeInterface $time): static
    {
        return clone($this, ['after' => self::utc($time)]);
    }

    /**
     * Posts published before the time (not at the time).
     */
    #[\NoDiscard]
    public function publishedBefore(DateTimeInterface $time): static
    {
        return clone($this, ['before' => self::utc($time)]);
    }

    /**
     * Sorts by post data or a meta field; further calls sort posts that are equal so far. A post without a stored
     * meta value sorts like the default of the field.
     */
    #[\NoDiscard]
    public function orderBy(PostOrder|Field $by, Direction $direction = Direction::Asc): static
    {
        return clone($this, ['order' => [...$this->order, [$by, $direction]]]);
    }

    /**
     * At most this many posts.
     */
    #[\NoDiscard]
    public function limit(int $limit): static
    {
        if ($limit < 1) {
            throw new InvalidArgumentException(sprintf('%s::query()->limit() needs at least 1, %d given.', $this->class, $limit));
        }

        return clone($this, ['limit' => $limit]);
    }

    /**
     * Skips this many posts (with limit()).
     */
    #[\NoDiscard]
    public function offset(int $offset): static
    {
        if ($offset < 0) {
            throw new InvalidArgumentException(sprintf('%s::query()->offset() needs at least 0, %d given.', $this->class, $offset));
        }

        return clone($this, ['offset' => $offset]);
    }

    /**
     * Caches the IDs of the result (and the number of posts) in the cache of the theme or plugin. The tags of the
     * post type and of the taxonomies in the conditions are added: changed posts, meta values and terms invalidate
     * the entry.
     *
     * @param int             $ttl  Lifetime in seconds, at least 1.
     * @param list<CacheTag>  $tags Further tags.
     * @param list<CacheVary> $vary Circumstances the result depends on beyond the query, e.g. filters of other plugins.
     */
    #[\NoDiscard]
    public function cache(int $ttl, array $tags = [], array $vary = []): static
    {
        return clone($this, ['cache' => ['ttl' => $ttl, 'tags' => $tags, 'vary' => $vary]]);
    }

    /**
     * The matching posts.
     *
     * @return list<T>
     */
    public function get(): array
    {
        if ($this->offset > 0 && $this->limit === null) {
            throw new LogicException(sprintf('%s::query()->offset() needs limit().', $this->class));
        }
        $paging = ['posts_per_page' => $this->limit ?? -1, 'no_found_rows' => true];
        if ($this->offset > 0) {
            $paging['offset'] = $this->offset;
        }

        return $this->posts($this->fetch($paging)['ids']);
    }

    /**
     * The first matching post, null without one.
     *
     * @return T|null
     */
    public function first(): ?AbstractPost
    {
        return (clone($this, ['limit' => 1]))->get()[0] ?? null;
    }

    /**
     * The number of all matching posts; limit() and offset() do not count.
     */
    public function count(): int
    {
        return (clone($this, ['order' => [], 'limit' => null, 'offset' => 0]))->fetch(['posts_per_page' => 1, 'no_found_rows' => false])['total'];
    }

    /**
     * One page of the matching posts with the number of all of them.
     *
     * @return PostPage<T>
     */
    public function paginate(int $perPage, int $page): PostPage
    {
        if ($perPage < 1 || $page < 1) {
            throw new InvalidArgumentException(sprintf('%s::query()->paginate() needs at least 1 post per page and page 1, %d and %d given.', $this->class, $perPage, $page));
        }
        if ($this->limit !== null || $this->offset > 0) {
            throw new LogicException(sprintf('%s::query()->paginate() sets the limit and offset itself; remove limit() and offset().', $this->class));
        }
        $result = $this->fetch(['posts_per_page' => $perPage, 'paged' => $page, 'no_found_rows' => false]);

        return new PostPage($this->posts($result['ids']), $result['total'], $page, $perPage);
    }

    /**
     * @internal The arguments of WP_Query, without paging.
     *
     * @return array<string, mixed>
     */
    public function toArgs(): array
    {
        $args = [
            'post_type' => $this->type,
            'post_status' => array_map(static fn(PostStatus $status): string => $status->value, $this->statuses),
            'fields' => 'ids',
            'ignore_sticky_posts' => true,
            'suppress_filters' => false,
        ];
        if ($this->authors !== []) {
            $args['author__in'] = $this->authors;
        }
        if ($this->parent !== null) {
            $args['post_parent'] = $this->parent;
        }
        if ($this->search !== null) {
            $args['s'] = $this->search;
        }
        $dates = [];
        foreach ([['post_date_gmt', $this->after, $this->before], ['post_modified_gmt', $this->modifiedAfter, $this->modifiedBefore]] as [$column, $after, $before]) {
            if ($after !== null || $before !== null) {
                $dates[] = array_filter(
                    ['column' => $column, 'after' => $after, 'before' => $before, 'inclusive' => false],
                    static fn(mixed $value): bool => $value !== null,
                );
            }
        }
        if ($dates !== []) {
            $args['date_query'] = $dates;
        }
        $args = [...$args, ...$this->args, ...$this->idArgs()];

        $meta = $this->templateClauses();
        $tax = $this->formatClauses();
        foreach ($this->conditions as $condition) {
            if (Group::kindOf($condition) === 'meta') {
                $meta[] = $this->metaClause($condition);
            } else {
                $tax[] = $this->taxClause($condition);
            }
        }
        if ($meta !== []) {
            $args['meta_query'] = ['relation' => 'AND', ...$meta];
        }
        if ($tax !== []) {
            $args['tax_query'] = ['relation' => 'AND', ...$tax];
        }

        return [...$args, ...$this->orderArgs()];
    }

    /**
     * @param array<string, mixed> $paging
     * @return array{ids: list<int>, total: int}
     */
    private function fetch(array $paging): array
    {
        // ids() without posts: WordPress would ignore an empty post__in.
        if ($this->ids === []) {
            return ['ids' => [], 'total' => 0];
        }
        $args = [...$this->toArgs(), ...$paging];
        if ($this->cache === null) {
            return self::run($args);
        }

        foreach ($this->order as [$by]) {
            if ($by === PostOrder::Random) {
                throw new LogicException(sprintf('%s::query(): a random order cannot be cached; remove cache() or PostOrder::Random.', $this->class));
            }
        }
        $cache = PostRegistry::postStore($this->class)->getCache();
        $entry = $cache->entry(sprintf('%s::query()->cache()', $this->class), $this->cache['ttl'], $this->cache['tags'], $this->cache['vary']);
        $taxonomies = $this->taxonomies($this->conditions);
        if ($this->formats !== []) {
            $taxonomies[] = 'post_format';
        }
        $automatic = [new PostTypeTag($this->type), ...array_map(static fn(string $taxonomy): TaxonomyTag => new TaxonomyTag($taxonomy), $taxonomies)];
        foreach ($automatic as $tag) {
            $key = CachePolicy::tagKey($tag, new TagNames())[0];
            if ($key !== null && !in_array($key, $entry['tags'], true)) {
                $entry['tags'][] = $key;
            }
        }

        /** @var array{ids: list<int>, total: int} The result of run(), possibly from an earlier request. */
        return $cache->fetch('query', $this->type, $args, $entry, static fn(): array => [self::run($args), true])[0];
    }

    /**
     * @param array<string, mixed> $args
     * @return array{ids: list<int>, total: int}
     */
    private static function run(array $args): array
    {
        $query = new WP_Query();
        $order = $args[MetaOrderClauses::QUERY_VAR] ?? null;
        $filter = null;
        if (is_array($order)) {
            /** @var list<array{meta: non-empty-string, default: string, cast: string, direction: 'ASC'|'DESC'}|array{column: string, direction: 'ASC'|'DESC'}> $order Built by orderArgs(). */
            $filter = static function (array $clauses, WP_Query $current) use ($query, $order): array {
                $wpdb = $GLOBALS['wpdb'] ?? null;
                if ($current !== $query || !$wpdb instanceof wpdb) {
                    return $clauses;
                }

                /** @var array<string, string> $clauses The SQL clauses. */
                return MetaOrderClauses::apply($clauses, $order, $wpdb);
            };
            add_filter('posts_clauses', $filter, PHP_INT_MAX, 2);
        }
        try {
            $query->query($args);
        } finally {
            if ($filter !== null) {
                remove_filter('posts_clauses', $filter, PHP_INT_MAX);
            }
        }

        $ids = array_values(array_map(static fn(mixed $id): int => is_numeric($id) ? (int) $id : 0, $query->posts ?? []));

        return ['ids' => $ids, 'total' => ($args['no_found_rows'] ?? true) === true ? count($ids) : $query->found_posts];
    }

    /**
     * @param list<int> $ids
     * @return list<T>
     */
    private function posts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        _prime_post_caches($ids);
        $posts = [];
        foreach ($ids as $id) {
            $post = get_post($id);
            if ($post instanceof WP_Post && $post->post_type === $this->type) {
                $posts[] = $this->class::fromWpPost($post);
            }
        }

        return $posts;
    }

    /**
     * @return array<string|int, mixed>
     */
    private function metaClause(Condition $condition): array
    {
        if ($condition instanceof Group) {
            return ['relation' => $condition->getRelation(), ...array_map($this->metaClause(...), $condition->getConditions())];
        }
        if (!$condition instanceof FieldCondition) {
            throw new LogicException(sprintf('%s is no condition on a meta field.', $condition::class));
        }
        $field = $this->field($condition->getField());
        $operator = $condition->getOperator();
        $values = $condition->getValues();
        if ($operator === 'IS NULL' || $operator === 'IS NOT NULL' || $values === []) {
            throw new LogicException(sprintf('%s::$%s: %s is a condition on a column of a table.', $condition->getField()->getClass(), $condition->getField()->getField(), $operator));
        }
        $clause = [
            'key' => $field['key'],
            'compare' => $operator,
            'value' => in_array($operator, ['IN', 'NOT IN', 'BETWEEN'], true) ? $values : $values[0],
            'type' => $condition->getField()->getCast(),
        ];
        // A meta value that is not stored is its default: if the default meets the condition, so does a missing value.
        if (!self::matches($condition, $field['default'])) {
            return $clause;
        }

        return ['relation' => 'OR', $clause, ['key' => $field['key'], 'compare' => 'NOT EXISTS']];
    }

    /**
     * Whether a stored meta value meets the condition, compared like the database does after the cast (strings
     * without regard to case, like the default collations).
     */
    private static function matches(FieldCondition $condition, mixed $stored): bool
    {
        $field = $condition->getField();
        $value = $field->normalize($stored);
        $values = array_map($field->normalize(...), $condition->getValues());

        return match ($condition->getOperator()) {
            '=' => $value === $values[0],
            '!=' => $value !== $values[0],
            '>' => $value > $values[0],
            '>=' => $value >= $values[0],
            '<' => $value < $values[0],
            '<=' => $value <= $values[0],
            'IN' => in_array($value, $values, true),
            'NOT IN' => !in_array($value, $values, true),
            'BETWEEN' => $value >= $values[0] && $value <= ($values[1] ?? $values[0]),
            'LIKE' => str_contains((string) $value, (string) $values[0]),
            'IS NULL', 'IS NOT NULL' => throw new LogicException('Meta fields have no null.'),
        };
    }

    /**
     * @return array<string|int, mixed>
     */
    private function taxClause(Condition $condition): array
    {
        if ($condition instanceof Group) {
            return ['relation' => $condition->getRelation(), ...array_map($this->taxClause(...), $condition->getConditions())];
        }
        if (!$condition instanceof TermCondition) {
            throw new LogicException(sprintf('%s is no condition on terms.', $condition::class));
        }

        return $condition->clause();
    }

    /**
     * @param list<Condition> $conditions
     * @return list<string>
     */
    private function taxonomies(array $conditions): array
    {
        $names = [];
        foreach ($conditions as $condition) {
            $found = match (true) {
                $condition instanceof TermCondition => [$condition->getTaxonomyName()],
                $condition instanceof Group => $this->taxonomies($condition->getConditions()),
                default => [],
            };
            array_push($names, ...$found);
        }

        return array_values(array_unique($names));
    }

    /**
     * Sorting by post data only is left to WordPress; with a meta field, MetaOrderClauses writes the whole order.
     *
     * @return array<string, mixed>
     */
    private function orderArgs(): array
    {
        if ($this->order === []) {
            return [];
        }
        $byMeta = false;
        foreach ($this->order as [$by]) {
            $byMeta = $byMeta || $by instanceof Field;
        }
        if (!$byMeta) {
            $orderBy = [];
            foreach ($this->order as [$by, $direction]) {
                /** @var PostOrder $by No field: checked above. */
                $orderBy[$by->value] = $direction->value;
            }

            return ['orderby' => $orderBy];
        }

        $specs = [];
        foreach ($this->order as [$by, $direction]) {
            if ($by instanceof PostOrder) {
                $specs[] = ['column' => $by->column(), 'direction' => $direction->value];
                continue;
            }
            $field = $this->field($by);
            $default = $field['default'];
            $specs[] = [
                'meta' => $field['key'],
                'default' => is_bool($default) ? (string) (int) $default : (is_scalar($default) ? (string) $default : ''),
                'cast' => $by->getCast(),
                'direction' => $direction->value,
            ];
        }

        return ['orderby' => 'none', MetaOrderClauses::QUERY_VAR => $specs];
    }

    /**
     * @return array{key: non-empty-string, default: mixed}
     */
    private function field(Field $field): array
    {
        return PostRegistry::metaStore($field->getClass())->queryField($this->class, $field);
    }

    #[\NoDiscard]
    private function withArg(string $name, mixed $value): static
    {
        return clone($this, ['args' => [...$this->args, $name => $value]]);
    }

    /**
     * @param array<AbstractPost|int> $parents
     * @return list<int>
     */
    private function parents(string $method, array $parents): array
    {
        if (!PostRegistry::postStore($this->class)->post($this->class)['hierarchical']) {
            throw new LogicException(sprintf('%s::query()->%s(): the post type is not hierarchical.', $this->class, $method));
        }
        if ($parents === []) {
            throw new InvalidArgumentException(sprintf('%s::query()->%s() needs at least one post.', $this->class, $method));
        }

        return array_map($this->id(...), array_values($parents));
    }

    /**
     * The meta clause of template() or defaultTemplate().
     *
     * @return list<array<string|int, mixed>>
     */
    private function templateClauses(): array
    {
        if ($this->templates === null) {
            return [];
        }
        if ($this->templates !== []) {
            return [['key' => self::TEMPLATE_KEY, 'compare' => 'IN', 'value' => $this->templates]];
        }

        return [['relation' => 'OR', ['key' => self::TEMPLATE_KEY, 'compare' => 'NOT EXISTS'], ['key' => self::TEMPLATE_KEY, 'compare' => 'IN', 'value' => ['', 'default']]]];
    }

    /**
     * The tax clause of format(): terms post-format-{format}; Standard has none.
     *
     * @return list<array<string|int, mixed>>
     */
    private function formatClauses(): array
    {
        if ($this->formats === []) {
            return [];
        }
        $slugs = [];
        $standard = false;
        foreach ($this->formats as $format) {
            if ($format === PostFormat::Standard) {
                $standard = true;
            } else {
                $slugs[] = 'post-format-' . $format->value;
            }
        }
        $clauses = [];
        if ($slugs !== []) {
            $clauses[] = ['taxonomy' => 'post_format', 'field' => 'slug', 'terms' => $slugs, 'operator' => 'IN'];
        }
        if ($standard) {
            $clauses[] = ['taxonomy' => 'post_format', 'operator' => 'NOT EXISTS'];
        }

        return count($clauses) === 1 ? $clauses : [['relation' => 'OR', ...$clauses]];
    }

    /**
     * post__in and post__not_in from ids(), exclude() and sticky(). WordPress ignores post__not_in next to post__in
     * (class-wp-query.php), so excluded posts are taken out of the IDs instead; no post has the ID 0, which keeps
     * an empty list from being ignored.
     *
     * @return array{post__in?: list<int>, post__not_in?: list<int>}
     */
    private function idArgs(): array
    {
        $in = $this->ids;
        $notIn = $this->exclude;
        if ($this->sticky !== null) {
            $sticky = self::stickyIds();
            if ($this->sticky) {
                $in = $in === null ? $sticky : array_values(array_intersect($in, $sticky));
            } else {
                $notIn = array_values(array_unique([...$notIn, ...$sticky]));
            }
        }
        if ($in === null) {
            return $notIn === [] ? [] : ['post__not_in' => $notIn];
        }
        $in = array_values(array_diff($in, $notIn));

        return ['post__in' => $in === [] ? [0] : $in];
    }

    /**
     * @return list<int>
     */
    private static function stickyIds(): array
    {
        $sticky = get_option('sticky_posts');

        return is_array($sticky) ? array_values(array_map(static fn(mixed $id): int => is_numeric($id) ? (int) $id : 0, $sticky)) : [];
    }

    private function id(AbstractPost|int $post): int
    {
        if (is_int($post)) {
            return $post;
        }

        return $post->getId() ?? throw new InvalidArgumentException(sprintf('%s::query(): the %s has not been saved yet.', $this->class, $post::class));
    }

    private static function utc(DateTimeInterface $time): string
    {
        return DateTimeImmutable::createFromInterface($time)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
