<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use BackedEnum;
use Closure;
use LogicException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Value\ValueFactory;

/**
 * The post types, taxonomies and meta classes of one instance at run time: loads
 * meta objects from the stored values and turns them back into storable values.
 *
 * Stored values are checked against their schema when they are loaded; an
 * invalid value is logged and the field keeps the default of its constructor
 * parameter, like settings. Before anything is written, every value is checked
 * again; save() writes nothing if one is invalid.
 *
 * @internal
 *
 * @phpstan-import-type PostClassEntry from PostTypeCollector
 * @phpstan-import-type TaxonomyEntry from PostTypeCollector
 * @phpstan-import-type MetaEntry from PostTypeCollector
 * @phpstan-import-type Value from ValueFactory
 * @phpstan-import-type ValueField from ValueFactory
 */
#[NoService]
final class PostStore
{
    /** @var array<non-empty-string, class-string<AbstractPost>> */
    private array $classesByType = [];

    /**
     * @param array<class-string<AbstractPost>, PostClassEntry> $posts
     * @param array<class-string, TaxonomyEntry>                $taxonomies
     * @param array<class-string, MetaEntry>                    $meta
     * @param array<class-string, array<string, ValueField>>    $values     Value descriptions of the manifest.
     */
    public function __construct(
        private readonly string $slug,
        private readonly array $posts,
        private readonly array $taxonomies,
        private readonly array $meta,
        private readonly array $values,
        private readonly ValueFactory $factory,
    ) {
        foreach ($posts as $class => $post) {
            $this->classesByType[$post['name']] = $class;
        }
    }

    /**
     * @return array<class-string<AbstractPost>, PostClassEntry>
     */
    public function getPosts(): array
    {
        return $this->posts;
    }

    /**
     * @return array<class-string, TaxonomyEntry>
     */
    public function getTaxonomies(): array
    {
        return $this->taxonomies;
    }

    /**
     * @return array<class-string, MetaEntry>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * The post class of a post type of this instance, null for other types.
     *
     * @return class-string<AbstractPost>|null
     */
    public function postClass(string $type): ?string
    {
        return $this->classesByType[$type] ?? null;
    }

    /**
     * @param class-string<AbstractPost> $class
     * @return PostClassEntry
     */
    public function post(string $class): array
    {
        return $this->posts[$class] ?? throw new LogicException(sprintf('%s is no post class of the %s instance; rebuild the manifest after code changes.', $class, $this->slug));
    }

    /**
     * The meta class of a post class by class or by name (`details` in templates).
     *
     * @param class-string<AbstractPost> $postClass
     * @return class-string
     */
    public function metaClass(string $postClass, string $classOrName): string
    {
        foreach ($this->post($postClass)['meta'] as $class) {
            if ($class === $classOrName || $this->meta[$class]['name'] === $classOrName) {
                return $class;
            }
        }

        throw new LogicException(sprintf(
            '%s is no meta class of %s; meta classes name their post classes in #[PostMeta(postTypes: [...])] (%s).',
            $classOrName,
            $postClass,
            implode(', ', array_map(fn(string $class): string => sprintf('%s "%s"', $class, $this->meta[$class]['name']), $this->post($postClass)['meta'])) ?: 'none',
        ));
    }

    /**
     * Creates the meta object of a post from its stored values (the defaults for a new post) and returns it with
     * the storable values it was created from.
     *
     * @param class-string $class
     * @return array{object, array<string, mixed>}
     */
    public function loadMeta(string $class, ?int $postId): array
    {
        $entry = $this->metaEntry($class);
        $data = [];
        if ($postId !== null) {
            foreach ($entry['fields'] as $name => $field) {
                // A missing value keeps the default of the constructor parameter.
                if (!metadata_exists('post', $postId, $field['key'])) {
                    continue;
                }
                $raw = get_post_meta($postId, $field['key'], true);
                // IDs (references, images) store no post as 0.
                if ($field['id'] && ($raw === '' || $raw === 0 || $raw === '0')) {
                    continue;
                }
                $valid = rest_validate_value_from_schema($raw, $field['schema'], $field['key']);
                if (is_wp_error($valid)) {
                    self::log(sprintf('the stored value of %s of post %d is invalid (%s); %s::$%s uses its default.', $field['key'], $postId, $valid->get_error_message(), $class, $name));
                    continue;
                }
                $data[$name] = rest_sanitize_value_from_schema($raw, $field['schema'], $field['key']);
            }
        }

        $object = $this->factory->create(['type' => 'object', 'class' => $class, 'nullable' => false], $data, $class);
        if (!is_object($object)) {
            throw new LogicException(sprintf('The meta class %s could not be created.', $class));
        }

        return [$object, $this->exportMeta($class, $object)];
    }

    /**
     * The storable values of a meta object by field: post objects become IDs, enums their values, value objects
     * arrays. A field holding a post without ID is an error.
     *
     * @param class-string $class
     * @return array<string, mixed>
     */
    public function exportMeta(string $class, object $meta): array
    {
        // Bound to the meta class, get_object_vars() also returns its private and protected fields; no reflection.
        $properties = Closure::bind(static fn(object $object): array => get_object_vars($object), null, $class)($meta);
        $raw = [];
        foreach ($this->fields($class) as $name => $field) {
            $raw[$name] = $this->export($field['value'], $properties[$name] ?? null, $class . '::$' . $name);
        }

        return $raw;
    }

    /**
     * Messages for every storable value that does not match its schema.
     *
     * @param class-string         $class
     * @param array<string, mixed> $raw
     * @return list<string>
     */
    public function validateMeta(string $class, array $raw): array
    {
        $errors = [];
        foreach ($this->metaEntry($class)['fields'] as $name => $field) {
            $value = $raw[$name] ?? null;
            if ($value === null && $field['id']) {
                continue;
            }
            $valid = rest_validate_value_from_schema($value, $field['schema'], $class . '::$' . $name);
            if (is_wp_error($valid)) {
                $errors[] = $valid->get_error_message();
            }
        }

        return $errors;
    }

    /**
     * Writes the values that differ from the values the object was loaded with. A missing ID (null) deletes the
     * meta key; WordPress filters rich text through the registered sanitize callback.
     *
     * @param class-string         $class
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $loaded
     */
    public function writeMeta(int $postId, string $class, array $raw, array $loaded, bool $new): void
    {
        foreach ($this->metaEntry($class)['fields'] as $name => $field) {
            $value = $raw[$name] ?? null;
            if (!$new && array_key_exists($name, $loaded) && $loaded[$name] === $value) {
                continue;
            }
            if ($value === null) {
                delete_post_meta($postId, $field['key']);
                continue;
            }
            // update_metadata() unslashes the value.
            update_post_meta($postId, $field['key'], wp_slash(rest_sanitize_value_from_schema($value, $field['schema'], $field['key'])));
        }
    }

    /**
     * @param class-string $class
     * @return MetaEntry
     */
    private function metaEntry(string $class): array
    {
        return $this->meta[$class] ?? throw new LogicException(sprintf('%s is no meta class of the %s instance; rebuild the manifest after code changes.', $class, $this->slug));
    }

    /**
     * @param class-string $class
     * @return array<string, ValueField>
     */
    private function fields(string $class): array
    {
        return $this->values[$class] ?? throw new LogicException(sprintf('%s is not in the manifest; rebuild the manifest after code changes.', $class));
    }

    /**
     * @param Value $value
     */
    private function export(array $value, mixed $data, string $path): mixed
    {
        if ($data === null) {
            return null;
        }
        if ($data instanceof AbstractPost) {
            return $data->getId() ?? throw new PostValidationException([sprintf('%s refers to a post that has not been saved yet; save it first.', $path)]);
        }
        if ($data instanceof BackedEnum) {
            return $data->value;
        }
        if ($value['type'] === 'list' && is_array($data)) {
            $list = [];
            foreach (array_values($data) as $index => $item) {
                $list[] = $this->export($value['items'], $item, sprintf('%s[%d]', $path, $index));
            }

            return $list;
        }
        if ($value['type'] === 'object' && is_object($data)) {
            $properties = get_object_vars($data);
            $object = [];
            foreach ($this->fields($value['class']) as $name => $field) {
                $object[$name] = $this->export($field['value'], $properties[$name] ?? null, $path . '.' . $name);
            }

            return $object;
        }

        return $data;
    }

    private static function log(string $message): void
    {
        error_log('wp-app-framework: post meta: ' . $message);
    }
}
