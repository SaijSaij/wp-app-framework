<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use ReflectionClass;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Project\ComposerProject;
use Saij\WpAppFramework\Query\MetaFields;
use Saij\WpAppFramework\Value\ValueSchemaBuilder;
use Throwable;

/**
 * Collects and validates post classes (`#[PostType]`), meta classes (`#[PostMeta]`)
 * and taxonomies (`#[Taxonomy]`) during the build and derives what WordPress
 * registers at run time: the arguments of register_post_type() and
 * register_taxonomy() and one meta key per field.
 *
 * @phpstan-import-type Control from ValueSchemaBuilder
 * @phpstan-import-type Field from ValueSchemaBuilder
 * @phpstan-import-type PostClass from ValueSchemaBuilder
 * @phpstan-type PostTypeArgs array{
 *     labels: array<string, string>,
 *     description: string,
 *     public: bool,
 *     show_ui: bool,
 *     show_in_rest: bool,
 *     supports: list<string>|false,
 *     hierarchical: bool,
 *     has_archive: bool|string,
 *     rewrite: array{slug: string},
 *     menu_icon?: string,
 *     menu_position?: int
 * }
 * @phpstan-type TaxonomyArgs array{
 *     labels: array<string, string>,
 *     description: string,
 *     public: bool,
 *     show_ui: bool,
 *     show_in_rest: bool,
 *     hierarchical: bool,
 *     rewrite: array{slug: string}
 * }
 * @phpstan-type PostClassEntry array{
 *     name: lowercase-string&non-empty-string,
 *     restBase: non-empty-string,
 *     builtin: bool,
 *     hierarchical: bool,
 *     args: PostTypeArgs|null,
 *     meta: list<class-string>
 * }
 * @phpstan-type TaxonomyEntry array{name: non-empty-string, builtin: bool, args: TaxonomyArgs|null, postTypes: list<non-empty-string>}
 * @phpstan-type MetaFieldEntry array{
 *     key: non-empty-string,
 *     type: 'string'|'boolean'|'integer'|'number'|'array'|'object',
 *     schema: array<string, mixed>,
 *     default: mixed,
 *     id: bool,
 *     richText: list<string>
 * }
 * @phpstan-type MetaEntry array{
 *     name: non-empty-string,
 *     title: string,
 *     postTypes: list<class-string<AbstractPost>>,
 *     fields: array<string, MetaFieldEntry>,
 *     controls: array<string, Control>
 * }
 */
#[NoService]
final class PostTypeCollector
{
    private const string NAME_PATTERN = '~^[a-z][a-z0-9_]*$~';

    private const string SLUG_PATTERN = '~^[a-z0-9][a-z0-9_-]*$~';

    /** Same limits as register_post_type() and register_taxonomy() (post.php, taxonomy.php). */
    private const int MAX_POST_TYPE_LENGTH = 20;

    private const int MAX_TAXONOMY_LENGTH = 32;

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param non-empty-string $namespace          Namespace of the project; names are `{namespace}_{name}`.
     * @param string           $frameworkDirectory Source directory of the framework, which declares no post types.
     */
    public function __construct(
        private readonly ValueSchemaBuilder $values,
        private readonly string $namespace,
        private readonly string $frameworkDirectory,
    ) {}

    /**
     * The post classes of the scanned classes with the name and REST base of their post type, for the value schemas;
     * invalid declarations are reported by collect().
     *
     * @param array<class-string, ReflectionClass<object>> $classes
     * @param non-empty-string                             $namespace
     * @return array<class-string, PostClass>
     */
    public static function postClasses(array $classes, string $namespace): array
    {
        $posts = [Post::class => ['name' => 'post', 'restBase' => 'posts'], Page::class => ['name' => 'page', 'restBase' => 'pages']];
        foreach ($classes as $class => $reflection) {
            $attribute = self::instance($reflection, PostType::class);
            if ($attribute instanceof PostType && $attribute->name !== '') {
                $name = ComposerProject::dataPrefix($namespace) . $attribute->name;
                $posts[$class] = ['name' => $name, 'restBase' => $name];
            }
        }

        return $posts;
    }

    /**
     * The taxonomy classes of the scanned classes with their name.
     *
     * @param array<class-string, ReflectionClass<object>> $classes
     * @param non-empty-string                             $namespace
     * @return array<class-string, non-empty-string>
     */
    public static function taxonomyClasses(array $classes, string $namespace): array
    {
        $taxonomies = [Category::class => 'category', Tag::class => 'post_tag'];
        foreach ($classes as $class => $reflection) {
            $attribute = self::instance($reflection, Taxonomy::class);
            if ($attribute instanceof Taxonomy && $attribute->name !== '') {
                $taxonomies[$class] = ComposerProject::dataPrefix($namespace) . $attribute->name;
            }
        }

        return $taxonomies;
    }

    /**
     * Post, meta, taxonomy and meta fields classes (#[MetaFields]): no services.
     *
     * @param array<class-string, ReflectionClass<object>> $classes
     * @return array<class-string, string> Class => what it is.
     */
    public static function nonServices(array $classes): array
    {
        $result = [];
        foreach ($classes as $class => $reflection) {
            $kind = match (true) {
                $reflection->isSubclassOf(AbstractPost::class) => 'a post class',
                $reflection->getAttributes(PostMeta::class) !== [] => 'a meta class',
                $reflection->getAttributes(Taxonomy::class) !== [], $class === Category::class, $class === Tag::class => 'a taxonomy class',
                $reflection->getAttributes(MetaFields::class) !== [] => 'a meta fields class',
                default => null,
            };
            if ($kind !== null) {
                $result[$class] = $kind;
            }
        }

        return $result;
    }

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     * @return array{
     *     posts: array<class-string<AbstractPost>, PostClassEntry>,
     *     taxonomies: array<class-string, TaxonomyEntry>,
     *     meta: array<class-string, MetaEntry>,
     *     rewrite: non-empty-string|null
     * }
     */
    public function collect(array $classes): array
    {
        /** @var array<class-string<AbstractPost>, PostClassEntry> $posts Meta and arguments are added below. */
        $posts = [
            Post::class => ['name' => 'post', 'restBase' => 'posts', 'builtin' => true, 'hierarchical' => false, 'args' => null, 'meta' => []],
            Page::class => ['name' => 'page', 'restBase' => 'pages', 'builtin' => true, 'hierarchical' => true, 'args' => null, 'meta' => []],
        ];
        /** @var array<class-string<AbstractPost>, PostType> $declarations */
        $declarations = [];
        $names = [];
        foreach ($classes as $class => $reflection) {
            $this->checkCombination($class, $reflection);
            $entry = $this->postClass($class, $reflection, $names);
            if ($entry !== null) {
                /** @var class-string<AbstractPost> $class Checked by postClass(). */
                $posts[$class] = $entry[0];
                $declarations[$class] = $entry[1];
            }
        }

        $meta = [];
        $metaNames = [];
        $keys = [];
        /** @var array<class-string, list<class-string>> $metaByPost */
        $metaByPost = [];
        foreach ($classes as $class => $reflection) {
            $entry = $this->metaClass($class, $reflection, $posts, $metaNames, $keys);
            if ($entry === null) {
                continue;
            }
            $meta[$class] = $entry;
            foreach ($entry['postTypes'] as $postClass) {
                $metaByPost[$postClass][] = $class;
            }
        }

        // WordPress' own taxonomies: registered by WordPress, named by the framework's classes.
        $taxonomies = [
            Category::class => ['name' => 'category', 'builtin' => true, 'args' => null, 'postTypes' => ['post']],
            Tag::class => ['name' => 'post_tag', 'builtin' => true, 'args' => null, 'postTypes' => ['post']],
        ];
        $taxonomyNames = [];
        foreach ($classes as $class => $reflection) {
            $entry = $this->taxonomy($class, $reflection, $posts, $taxonomyNames);
            if ($entry !== null) {
                $taxonomies[$class] = $entry;
            }
        }

        $rewrite = [];
        $entries = [];
        foreach ($posts as $class => $post) {
            $declaration = $declarations[$class] ?? null;
            $args = $declaration === null ? null : self::postTypeArgs($declaration, isset($metaByPost[$class]));
            $entries[$class] = [...$post, 'args' => $args, 'meta' => $metaByPost[$class] ?? []];
            if ($declaration !== null && $args !== null && $declaration->public) {
                $rewrite[] = ['post', $post['name'], $args['rewrite'], $args['has_archive'], $declaration->hierarchical];
            }
        }
        foreach ($taxonomies as $taxonomy) {
            if ($taxonomy['args'] !== null && $taxonomy['args']['public'] === true) {
                $rewrite[] = ['taxonomy', $taxonomy['name'], $taxonomy['args']['rewrite'], $taxonomy['postTypes'], $taxonomy['args']['hierarchical']];
            }
        }

        return [
            'posts' => $entries,
            'taxonomies' => $taxonomies,
            'meta' => $meta,
            'rewrite' => $rewrite === [] ? null : hash('xxh128', (string) json_encode($rewrite)),
        ];
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param class-string            $class
     * @param ReflectionClass<object> $reflection
     */
    private function checkCombination(string $class, ReflectionClass $reflection): void
    {
        $attributes = array_filter([
            '#[PostType]' => $reflection->getAttributes(PostType::class) !== [],
            '#[PostMeta]' => $reflection->getAttributes(PostMeta::class) !== [],
            '#[Taxonomy]' => $reflection->getAttributes(Taxonomy::class) !== [],
        ]);
        if (count($attributes) > 1) {
            $this->errors[] = sprintf('%s has %s; a class is a post class, a meta class or a taxonomy, not several.', $class, implode(' and ', array_keys($attributes)));
        }
        if ($attributes !== [] && str_starts_with((string) realpath((string) $reflection->getFileName()), $this->frameworkDirectory . '/')) {
            $this->errors[] = sprintf('%s has %s, but the framework declares no post types, meta or taxonomies; they belong to the theme or plugin.', $class, implode(' and ', array_keys($attributes)));
        }
    }

    /**
     * @param class-string                          $class
     * @param ReflectionClass<object>               $reflection
     * @param array<non-empty-string, class-string> $names       Full names already used.
     * @return array{PostClassEntry, PostType}|null
     */
    private function postClass(string $class, ReflectionClass $reflection, array &$names): ?array
    {
        $isPost = $reflection->isSubclassOf(AbstractPost::class);
        $attribute = $reflection->getAttributes(PostType::class)[0] ?? null;
        if ($class === Post::class || $class === Page::class) {
            return null;
        }
        if ($attribute === null) {
            if ($isPost && !$reflection->isAbstract()) {
                $this->errors[] = sprintf('%s extends %s, but has no #[PostType]; post classes declare their post type (WordPress\' own types are Post and Page).', $class, AbstractPost::class);
            }

            return null;
        }

        try {
            $postType = $attribute->newInstance();
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[PostType] on %s: %s', $class, $exception->getMessage());

            return null;
        }

        $name = strtolower(ComposerProject::dataPrefix($this->namespace) . $postType->name);
        $ownProperties = array_values(array_filter(
            $reflection->getProperties(),
            static fn(\ReflectionProperty $property): bool => !$property->isStatic() && $property->getDeclaringClass()->getName() !== AbstractPost::class,
        ));
        $error = match (true) {
            !$isPost => sprintf('#[PostType] on %s: the class must extend %s; it is the object of a post of the type.', $class, AbstractPost::class),
            $reflection->isAbstract() => sprintf('#[PostType] on %s: the class must not be abstract.', $class),
            $ownProperties !== [] => sprintf(
                '%s declares the property $%s; post classes keep no data of their own (it would not be stored). Declare the fields in a meta class (#[PostMeta]).',
                $class,
                $ownProperties[0]->getName(),
            ),
            preg_match(self::NAME_PATTERN, $postType->name) !== 1 => sprintf('#[PostType] on %s has the invalid name "%s": use lowercase letters, digits and "_", starting with a letter, e.g. "event".', $class, $postType->name),
            strlen($name) > self::MAX_POST_TYPE_LENGTH => sprintf(
                '#[PostType] on %s results in the post type "%s", which is longer than %d characters (the limit of register_post_type()); use a shorter name or namespace.',
                $class,
                $name,
                self::MAX_POST_TYPE_LENGTH,
            ),
            isset($names[$name]) => sprintf('#[PostType] on %s uses the name "%s", which %s already uses.', $class, $postType->name, $names[$name]),
            default => $this->labelsError('#[PostType]', $class, $postType->label, $postType->singularLabel)
                ?? $this->slugError('#[PostType]', $class, $postType->slug)
                ?? $this->supportsError($class, $postType->supports),
        };
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }
        $names[$name] = $class;

        return [
            ['name' => $name, 'restBase' => $name, 'builtin' => false, 'hierarchical' => $postType->hierarchical, 'args' => null, 'meta' => []],
            $postType,
        ];
    }

    /**
     * @param class-string                                      $class
     * @param ReflectionClass<object>                           $reflection
     * @param array<class-string, PostClassEntry>               $posts
     * @param array<string, array<string, class-string>>        $names      Meta names by post class.
     * @param array<string, array<string, string>>              $keys       Meta keys by post class: key => field.
     * @return MetaEntry|null
     */
    private function metaClass(string $class, ReflectionClass $reflection, array $posts, array &$names, array &$keys): ?array
    {
        $attribute = $reflection->getAttributes(PostMeta::class)[0] ?? null;
        if ($attribute === null) {
            return null;
        }
        try {
            $meta = $attribute->newInstance();
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[PostMeta] on %s: %s', $class, $exception->getMessage());

            return null;
        }

        $postTypes = $this->classList('#[PostMeta]', $class, $meta->postTypes, $posts, 'post class (#[PostType], Post or Page)');
        $error = match (true) {
            preg_match(self::NAME_PATTERN, $meta->name) !== 1 => sprintf('#[PostMeta] on %s has the invalid name "%s": use lowercase letters, digits and "_", starting with a letter, e.g. "details".', $class, $meta->name),
            trim($meta->title) === '' => sprintf('#[PostMeta] on %s has an empty title.', $class),
            default => null,
        };
        if ($error !== null) {
            $this->errors[] = $error;
        }
        $fields = $this->values->metaFields($class, sprintf('#[PostMeta] on %s', $class));
        if ($error !== null || $postTypes === null || $fields === null) {
            return null;
        }

        $entries = [];
        $controls = [];
        $valid = true;
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();
            $location = sprintf('parameter $%s of meta class %s::__construct()', $name, $class);
            $field = $fields[$name];
            $id = self::isId($field);
            if (!$parameter->isDefaultValueAvailable()) {
                $this->errors[] = sprintf('%s needs a default value: a meta field is missing until it is saved.', $location);
                $valid = false;
                continue;
            }
            if (is_array($field['schema']['type'] ?? null) && !$id) {
                $this->errors[] = sprintf(
                    '%s is nullable; the REST API of WordPress leaves out meta fields whose type allows null. Use a default value instead; only references and images (IDs) may be null.',
                    $location,
                );
                $valid = false;
                continue;
            }
            $key = ComposerProject::dataPrefix($this->namespace) . strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])/', '_$1', $name));
            $schema = $field['schema'];
            $default = ValueSchemaBuilder::export($parameter->getDefaultValue());
            if ($id) {
                // IDs: 0 (or a missing value) is no object.
                $schema['type'] = 'integer';
                $default ??= 0;
            }
            $schema['default'] = $default;
            /** @var 'string'|'boolean'|'integer'|'number'|'array'|'object' $type A single type: nullable types are rejected above. */
            $type = $schema['type'];
            $entries[$name] = [
                'key' => $key,
                'type' => $type,
                'schema' => $schema,
                'default' => $default,
                'id' => $id,
                'richText' => array_values(array_map(
                    static fn(array $editorField): string => $editorField['path'],
                    array_filter($field['fields'], static fn(array $editorField): bool => $editorField['kind'] === 'rich'),
                )),
            ];
            if ($field['control'] !== null) {
                $controls[$name] = $field['control'];
            }
            foreach ($postTypes as $postClass) {
                $other = $keys[$postClass][$key] ?? null;
                if ($other !== null) {
                    $this->errors[] = sprintf('%s uses the meta key "%s" on %s, which %s already uses.', $location, $key, $postClass, $other);
                    $valid = false;
                }
                $keys[$postClass][$key] = sprintf('%s::$%s', $class, $name);
            }
        }
        foreach ($postTypes as $postClass) {
            if (isset($names[$postClass][$meta->name])) {
                $this->errors[] = sprintf('#[PostMeta] on %s uses the name "%s" for %s, which %s already uses.', $class, $meta->name, $postClass, $names[$postClass][$meta->name]);
                $valid = false;
            }
            $names[$postClass][$meta->name] = $class;
        }
        if (!$valid) {
            return null;
        }

        return ['name' => $meta->name, 'title' => $meta->title, 'postTypes' => $postTypes, 'fields' => $entries, 'controls' => $controls];
    }

    /**
     * @param class-string                        $class
     * @param ReflectionClass<object>             $reflection
     * @param array<class-string, PostClassEntry> $posts
     * @param array<string, class-string>         $names
     * @return TaxonomyEntry|null
     */
    private function taxonomy(string $class, ReflectionClass $reflection, array $posts, array &$names): ?array
    {
        $attribute = $reflection->getAttributes(Taxonomy::class)[0] ?? null;
        if ($attribute === null) {
            return null;
        }
        try {
            $taxonomy = $attribute->newInstance();
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[Taxonomy] on %s: %s', $class, $exception->getMessage());

            return null;
        }

        $name = ComposerProject::dataPrefix($this->namespace) . $taxonomy->name;
        $postTypes = $this->classList('#[Taxonomy]', $class, $taxonomy->postTypes, $posts, 'post class (#[PostType], Post or Page)');
        $error = match (true) {
            ($reflection->getConstructor()?->getNumberOfParameters() ?? 0) > 0 => sprintf('#[Taxonomy] on %s: the class has constructor parameters; taxonomy classes declare no fields (term meta is not supported yet).', $class),
            preg_match(self::NAME_PATTERN, $taxonomy->name) !== 1 => sprintf('#[Taxonomy] on %s has the invalid name "%s": use lowercase letters, digits and "_", starting with a letter, e.g. "genre".', $class, $taxonomy->name),
            strlen($name) > self::MAX_TAXONOMY_LENGTH => sprintf(
                '#[Taxonomy] on %s results in the taxonomy "%s", which is longer than %d characters (the limit of register_taxonomy()); use a shorter name or namespace.',
                $class,
                $name,
                self::MAX_TAXONOMY_LENGTH,
            ),
            isset($names[$name]) => sprintf('#[Taxonomy] on %s uses the name "%s", which %s already uses.', $class, $taxonomy->name, $names[$name]),
            default => $this->labelsError('#[Taxonomy]', $class, $taxonomy->label, $taxonomy->singularLabel) ?? $this->slugError('#[Taxonomy]', $class, $taxonomy->slug),
        };
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }
        if ($postTypes === null) {
            return null;
        }
        $names[$name] = $class;

        $typeNames = array_map(static fn(string $postClass): string => $posts[$postClass]['name'], $postTypes);

        return ['name' => $name, 'builtin' => false, 'args' => self::taxonomyArgs($taxonomy), 'postTypes' => $typeNames];
    }

    /**
     * @param class-string                        $class
     * @param array<mixed>                        $list
     * @param array<class-string, PostClassEntry> $posts
     * @return list<class-string<AbstractPost>>|null
     */
    private function classList(string $attribute, string $class, array $list, array $posts, string $expected): ?array
    {
        if ($list === [] || !array_is_list($list)) {
            $this->errors[] = sprintf('%s on %s: postTypes must be a non-empty list of post classes, e.g. [Event::class, Post::class].', $attribute, $class);

            return null;
        }
        $result = [];
        foreach ($list as $item) {
            if (!is_string($item) || !isset($posts[$item])) {
                $this->errors[] = sprintf('%s on %s: postTypes contains %s, which is no %s of this theme or plugin.', $attribute, $class, is_string($item) ? $item : get_debug_type($item), $expected);

                return null;
            }
            if (in_array($item, $result, true)) {
                $this->errors[] = sprintf('%s on %s: postTypes lists %s twice.', $attribute, $class, $item);

                return null;
            }
            /** @var class-string<AbstractPost> $item */
            $result[] = $item;
        }

        return $result;
    }

    private function labelsError(string $attribute, string $class, string $label, string $singularLabel): ?string
    {
        return trim($label) === '' || trim($singularLabel) === ''
            ? sprintf('%s on %s needs a label and a singularLabel.', $attribute, $class)
            : null;
    }

    private function slugError(string $attribute, string $class, ?string $slug): ?string
    {
        return $slug !== null && preg_match(self::SLUG_PATTERN, $slug) !== 1
            ? sprintf('%s on %s has the invalid slug "%s": use lowercase letters, digits, "-" and "_".', $attribute, $class, $slug)
            : null;
    }

    /**
     * @param array<mixed> $supports
     */
    private function supportsError(string $class, array $supports): ?string
    {
        foreach ($supports as $support) {
            if (!$support instanceof PostTypeSupport) {
                return sprintf('#[PostType] on %s: supports must be a list of PostTypeSupport cases, %s given.', $class, get_debug_type($support));
            }
        }

        return null;
    }

    /**
     * Whether a meta field holds an ID that may be null: a reference or an image.
     *
     * @param Field $field
     */
    private static function isId(array $field): bool
    {
        if ($field['value']['type'] !== 'int' || !$field['value']['nullable']) {
            return false;
        }

        return isset($field['value']['reference'])
            || array_filter($field['fields'], static fn(array $editorField): bool => $editorField['kind'] === 'image' && $editorField['path'] === '') !== [];
    }

    /**
     * @return PostTypeArgs
     */
    private static function postTypeArgs(PostType $postType, bool $meta): array
    {
        $singular = $postType->singularLabel;
        $plural = $postType->label;
        $labels = [
            'name' => $plural,
            'singular_name' => $singular,
            'add_new' => 'Add New ' . $singular,
            'add_new_item' => 'Add New ' . $singular,
            'edit_item' => 'Edit ' . $singular,
            'new_item' => 'New ' . $singular,
            'view_item' => 'View ' . $singular,
            'view_items' => 'View ' . $plural,
            'search_items' => 'Search ' . $plural,
            'not_found' => sprintf('No %s found.', strtolower($plural)),
            'not_found_in_trash' => sprintf('No %s found in Trash.', strtolower($plural)),
            'parent_item_colon' => sprintf('Parent %s:', $singular),
            'all_items' => 'All ' . $plural,
            'archives' => $singular . ' Archives',
            'attributes' => $singular . ' Attributes',
            'menu_name' => $plural,
            'item_published' => $singular . ' published.',
            'item_updated' => $singular . ' updated.',
        ];
        $labels = [...$labels, ...self::labelOverrides($postType->labels)];
        $slug = $postType->slug ?? str_replace('_', '-', $postType->name);
        $supports = array_values(array_map(static fn(PostTypeSupport $support): string => $support->value, $postType->supports));
        if ($meta) {
            // Without custom-fields the REST API has no meta field (WP_REST_Posts_Controller::get_item_schema()).
            $supports[] = 'custom-fields';
        }
        $args = [
            'labels' => $labels,
            'description' => $postType->description,
            'public' => $postType->public,
            'show_ui' => $postType->showUi,
            'show_in_rest' => true,
            'supports' => $supports === [] ? false : $supports,
            'hierarchical' => $postType->hierarchical,
            'has_archive' => $postType->archive ? $slug : false,
            'rewrite' => ['slug' => $slug],
        ];
        if ($postType->menuIcon !== null) {
            $args['menu_icon'] = $postType->menuIcon;
        }
        if ($postType->menuPosition !== null) {
            $args['menu_position'] = $postType->menuPosition;
        }

        return $args;
    }

    /**
     * @return TaxonomyArgs
     */
    private static function taxonomyArgs(Taxonomy $taxonomy): array
    {
        $singular = $taxonomy->singularLabel;
        $plural = $taxonomy->label;
        $labels = [
            'name' => $plural,
            'singular_name' => $singular,
            'search_items' => 'Search ' . $plural,
            'all_items' => 'All ' . $plural,
            'parent_item' => 'Parent ' . $singular,
            'parent_item_colon' => sprintf('Parent %s:', $singular),
            'edit_item' => 'Edit ' . $singular,
            'view_item' => 'View ' . $singular,
            'update_item' => 'Update ' . $singular,
            'add_new_item' => 'Add New ' . $singular,
            'new_item_name' => sprintf('New %s Name', $singular),
            'not_found' => sprintf('No %s found.', strtolower($plural)),
            'menu_name' => $plural,
            'back_to_items' => '← Go to ' . $plural,
        ];

        return [
            'labels' => [...$labels, ...self::labelOverrides($taxonomy->labels)],
            'description' => $taxonomy->description,
            'public' => $taxonomy->public,
            'show_ui' => $taxonomy->showUi,
            'show_in_rest' => true,
            'hierarchical' => $taxonomy->hierarchical,
            'rewrite' => ['slug' => $taxonomy->slug ?? str_replace('_', '-', $taxonomy->name)],
        ];
    }

    /**
     * @return array<string, string> Labels set in the value object, by WordPress key.
     */
    private static function labelOverrides(PostTypeLabels|TaxonomyLabels|null $labels): array
    {
        $overrides = [];
        foreach (get_object_vars($labels ?? new PostTypeLabels()) as $property => $value) {
            if (is_string($value)) {
                $overrides[strtolower((string) preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $property))] = $value;
            }
        }

        return $overrides;
    }

    /**
     * @template T of object
     * @param ReflectionClass<object> $reflection
     * @param class-string<T>         $attribute
     * @return T|null
     */
    private static function instance(ReflectionClass $reflection, string $attribute): ?object
    {
        try {
            return ($reflection->getAttributes($attribute)[0] ?? null)?->newInstance();
        } catch (Throwable) {
            return null;
        }
    }
}
