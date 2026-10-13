<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Saij\WpAppFramework\Query\PostQuery;
use WP_Post;
use WP_Term;

/**
 * The object of a post: a class with `#[PostType]` extends it, and the framework
 * provides Post and Page for the post types of WordPress.
 *
 * A post object is loaded with `Event::get($id)` or `Event::find($id)` and created
 * with `new Event()`. It holds the data of the post (title, content, status, …) and
 * its meta objects (`meta(EventDetails::class)`); `save()` checks every meta value
 * against its schema and writes the post and the changed meta values.
 *
 * Every get() and find() returns a new object; changes are visible to others only
 * after save(). save() checks no capabilities, like wp_update_post().
 */
abstract class AbstractPost
{
    private ?int $id = null;

    /** The store of the instance that created the object; meta names in templates are those of this instance. */
    private ?PostStore $store = null;

    /** @var array<string, mixed> Fields as loaded (WP_Post properties). */
    private array $data = [];

    /**
     * @var array{post_title?: string, post_content?: string, post_excerpt?: string, post_status?: string, post_name?: string, post_author?: int, post_parent?: int}
     *     Changed fields, by WP_Post property.
     */
    private array $changes = [];

    /** @var array<class-string, array{object, array<string, mixed>}> Loaded meta objects with the values they were loaded with. */
    private array $meta = [];

    /** @var array<string, list<int>> Term IDs to set on save(), by taxonomy name. */
    private array $terms = [];

    /**
     * Creates a new post of the type; it is stored by save().
     */
    final public function __construct() {}

    /**
     * The post with the ID.
     *
     * @throws InvalidArgumentException If it does not exist or has another post type.
     */
    public static function get(int $id): static
    {
        return static::find($id) ?? throw new InvalidArgumentException(sprintf(
            'There is no %s with the ID %d (post type %s).',
            static::class,
            $id,
            PostRegistry::postStore(static::class)->post(static::class)['name'],
        ));
    }

    /**
     * The post with the ID, null if it does not exist or has another post type.
     */
    public static function find(int $id): ?static
    {
        // The class must belong to a booted instance, whether the post exists or not.
        $type = PostRegistry::postStore(static::class)->post(static::class)['name'];
        $post = get_post($id);

        return $post instanceof WP_Post && $post->post_type === $type ? static::fromWpPost($post) : null;
    }

    /**
     * A query for posts of the type: published posts, unsorted conditions, see PostQuery.
     *
     * @return PostQuery<static>
     */
    public static function query(): PostQuery
    {
        return PostQuery::of(static::class);
    }

    /**
     * @internal Creates the object of a post whose type belongs to the class.
     */
    public static function fromWpPost(WP_Post $post, ?PostStore $store = null): static
    {
        $object = new static();
        $object->id = $post->ID;
        $object->data = self::data($post);
        $object->store = $store;

        return $object;
    }

    /**
     * The ID, null before the post has been saved.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->string('post_title');
    }

    public function setTitle(string $title): void
    {
        $this->changes['post_title'] = $title;
    }

    /**
     * The content as stored: block markup.
     */
    public function getContent(): string
    {
        return $this->string('post_content');
    }

    public function setContent(string $content): void
    {
        $this->changes['post_content'] = $content;
    }

    /**
     * The manual excerpt as stored; empty without one.
     */
    public function getExcerpt(): string
    {
        return $this->string('post_excerpt');
    }

    public function setExcerpt(string $excerpt): void
    {
        $this->changes['post_excerpt'] = $excerpt;
    }

    /**
     * The status; a new post is a draft.
     */
    public function getStatus(): PostStatus
    {
        $status = $this->string('post_status');
        if ($status === '') {
            return PostStatus::Draft;
        }

        return PostStatus::tryFrom($status) ?? throw new LogicException(sprintf('Post %d has the status "%s", which PostStatus does not know.', $this->id ?? 0, $status));
    }

    public function setStatus(PostStatus $status): void
    {
        $this->changes['post_status'] = $status->value;
    }

    /**
     * The slug (post_name), empty until WordPress assigns one.
     */
    public function getSlug(): string
    {
        return $this->string('post_name');
    }

    public function setSlug(string $slug): void
    {
        $this->changes['post_name'] = $slug;
    }

    /**
     * The publication date in UTC, null for posts without one (drafts).
     */
    public function getDate(): ?DateTimeImmutable
    {
        return self::date($this->data['post_date_gmt'] ?? null);
    }

    /**
     * The date of the last change in UTC, null before the post has been saved.
     */
    public function getModified(): ?DateTimeImmutable
    {
        return self::date($this->data['post_modified_gmt'] ?? null);
    }

    /**
     * The ID of the author, null without author.
     */
    public function getAuthorId(): ?int
    {
        $author = self::int($this->changes['post_author'] ?? $this->data['post_author'] ?? 0);

        return $author > 0 ? $author : null;
    }

    public function setAuthorId(int $author): void
    {
        $this->changes['post_author'] = $author;
    }

    /**
     * The parent post (hierarchical post types), null without parent.
     */
    public function getParent(): ?static
    {
        $this->checkHierarchical('getParent()');
        $parent = self::int($this->changes['post_parent'] ?? $this->data['post_parent'] ?? 0);

        return $parent > 0 ? static::find($parent) : null;
    }

    /**
     * Sets the parent post (hierarchical post types); the parent must have been saved.
     */
    public function setParent(?self $parent): void
    {
        $this->checkHierarchical('setParent()');
        if ($parent !== null && !$parent instanceof static) {
            throw new InvalidArgumentException(sprintf('The parent of a %s must be a %s, %s given.', static::class, static::class, $parent::class));
        }
        if ($parent !== null && $parent->getId() === null) {
            throw new InvalidArgumentException(sprintf('The parent of a %s must have been saved.', static::class));
        }
        $this->changes['post_parent'] = $parent?->getId() ?? 0;
    }

    /**
     * The permalink, null before the post has been saved.
     */
    public function getUrl(): ?string
    {
        $url = $this->id === null ? false : get_permalink($this->id);

        return is_string($url) ? $url : null;
    }

    /**
     * The terms of a taxonomy (a class with #[Taxonomy]), as stored; changes by setTerms() count after save().
     *
     * @param class-string $taxonomy
     * @return list<WP_Term>
     */
    public function getTerms(string $taxonomy): array
    {
        if ($this->id === null) {
            return [];
        }
        $terms = wp_get_object_terms($this->id, PostRegistry::taxonomyName($taxonomy));
        if (!is_array($terms)) {
            throw new RuntimeException(sprintf('The terms of %s of post %d could not be read: %s', $taxonomy, $this->id, $terms->get_error_message()));
        }

        return array_values(array_filter($terms, static fn(mixed $term): bool => $term instanceof WP_Term));
    }

    /**
     * Sets the terms of a taxonomy (a class with #[Taxonomy]); stored by save().
     *
     * @param class-string $taxonomy
     * @param list<int>    $termIds
     */
    public function setTerms(string $taxonomy, array $termIds): void
    {
        $this->terms[PostRegistry::taxonomyName($taxonomy)] = $termIds;
    }

    /**
     * The meta object of a meta class (#[PostMeta] naming this post class), loaded on first use; the same object
     * until the post object is gone. In templates the meta class is named by its #[PostMeta] name:
     * `post.meta('details')`.
     *
     * @template T of object
     * @param class-string<T>|string $class The meta class, or its #[PostMeta] name.
     * @return ($class is class-string<T> ? T : object)
     */
    public function meta(string $class): object
    {
        // A class names its own instance; a name (templates) is looked up in the instance that created the object.
        $store = class_exists($class) ? PostRegistry::metaStore($class) : $this->store();
        $metaClass = $store->metaClass(static::class, $class);
        if (!isset($this->meta[$metaClass])) {
            $this->meta[$metaClass] = PostRegistry::metaStore($metaClass)->loadMeta($metaClass, $this->id);
        }

        return $this->meta[$metaClass][0];
    }

    /**
     * Stores the post: checks the loaded meta objects against their schemas (nothing is written if a value is
     * invalid), inserts or updates the post and writes the changed meta values and terms.
     *
     * @throws PostValidationException If a meta value is invalid.
     * @throws RuntimeException        If WordPress cannot save the post.
     */
    public function save(): void
    {
        $exported = [];
        $errors = [];
        foreach ($this->meta as $class => [$object]) {
            $store = PostRegistry::metaStore($class);
            $exported[$class] = $store->exportMeta($class, $object);
            array_push($errors, ...$store->validateMeta($class, $exported[$class]));
        }
        if ($errors !== []) {
            throw new PostValidationException($errors);
        }

        $new = $this->id === null;
        if ($new) {
            $id = wp_insert_post(wp_slash([
                'post_type' => $this->store()->post(static::class)['name'],
                'post_status' => PostStatus::Draft->value,
                ...$this->changes,
            ]), true);
        } elseif ($this->changes !== []) {
            $id = wp_update_post(wp_slash(['ID' => $this->id, ...$this->changes]), true);
        } else {
            $id = $this->id;
        }
        if (!is_int($id) || $id === 0) {
            throw new RuntimeException(sprintf('The %s could not be saved: %s', static::class, is_int($id) ? 'WordPress returned no ID' : $id->get_error_message()));
        }

        foreach ($exported as $class => $raw) {
            [$object, $loaded] = $this->meta[$class];
            PostRegistry::metaStore($class)->writeMeta($id, $class, $raw, $loaded, $new);
            $this->meta[$class] = [$object, $raw];
        }
        foreach ($this->terms as $taxonomy => $termIds) {
            $result = wp_set_object_terms($id, $termIds, $taxonomy);
            if (!is_array($result)) {
                throw new RuntimeException(sprintf('The terms of %s of post %d could not be saved: %s', $taxonomy, $id, $result->get_error_message()));
            }
        }

        $post = get_post($id);
        $this->id = $id;
        $this->data = $post instanceof WP_Post ? self::data($post) : [...$this->data, ...$this->changes];
        $this->changes = [];
        $this->terms = [];
    }

    /**
     * Moves the post to the trash, or deletes it with $force.
     */
    public function delete(bool $force = false): void
    {
        if ($this->id === null) {
            throw new LogicException(sprintf('The %s has not been saved, so it cannot be deleted.', static::class));
        }
        if (!wp_delete_post($this->id, $force) instanceof WP_Post) {
            throw new RuntimeException(sprintf('Post %d could not be deleted.', $this->id));
        }
    }

    /**
     * The WordPress post, null before the post has been saved.
     */
    public function toWpPost(): ?WP_Post
    {
        $post = $this->id === null ? null : get_post($this->id);

        return $post instanceof WP_Post ? $post : null;
    }

    private function store(): PostStore
    {
        return $this->store ??= PostRegistry::postStore(static::class);
    }

    private function string(string $field): string
    {
        $value = $this->changes[$field] ?? $this->data[$field] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private function checkHierarchical(string $method): void
    {
        if (!$this->store()->post(static::class)['hierarchical']) {
            throw new LogicException(sprintf('%s::%s: the post type is not hierarchical.', static::class, $method));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function data(WP_Post $post): array
    {
        /** @var array<string, mixed> The public properties of WP_Post. */
        return get_object_vars($post);
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

        return $date === false ? null : $date;
    }
}
