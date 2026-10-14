<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use BackedEnum;
use InvalidArgumentException;
use LogicException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Post\AbstractPost;
use WP_Post;
use WP_Term;
use WP_User;

/**
 * The only way from raw values to typed values: block attribute objects, value
 * objects, enums and lists. Driven by the value descriptions of the manifest,
 * without reflection.
 *
 * Values are expected to be validated against their schema already (WordPress
 * does that for block attributes and REST arguments); a value that still does
 * not match its type is an error, never coerced silently.
 *
 * @internal
 *
 * References (`#[PostReference]`, `#[TermReference]`, `#[UserReference]`)
 * resolve to their ID only if the object exists and is of an allowed type
 * (posts also only if publicly viewable, except in renders for the block
 * editor); otherwise they become null or are left out of their list. A
 * reference to a post class (a field typed as the class) becomes the post
 * object; it resolves if the post has the type of the class and is published,
 * also for post types that are not public (data types), and in renders for the
 * block editor in every status.
 *
 * A `Link` to a post, term or media file gets the current URL of the object
 * under the same conditions as a reference (attachments if they are public),
 * else an empty URL; its kind and ID stay. A link whose kind and ID do not
 * match (a free URL with an ID, a linked object without one) is an error.
 *
 * @phpstan-type Reference array{target: 'post'|'term'|'user', subtypes: list<string>|null, class?: class-string<AbstractPost>}
 * @phpstan-type ClassValue array{type: 'enum'|'object', class: class-string, nullable: bool}
 * @phpstan-type ReferenceValue array{type: 'int', nullable: bool, reference: Reference}
 * @phpstan-type Value array{type: 'int'|'float'|'string'|'bool', nullable: bool}
 *     |ReferenceValue
 *     |ClassValue
 *     |array{type: 'list', items: ClassValue|ReferenceValue, nullable: bool}
 * @phpstan-type ValueField array{value: Value, required: bool}
 */
#[NoService]
final readonly class ValueFactory
{
    /**
     * @param array<class-string, array<string, ValueField>> $classes Value classes: constructor parameter => field.
     * @param RenderContext|null                             $context Whether a render is for the block editor (references to
     *                                                                posts that are not public stay there).
     */
    public function __construct(
        private array $classes,
        private ?RenderContext $context = null,
    ) {}

    /**
     * Creates a block attribute object from the attributes WordPress passes to
     * the render callback. WordPress validates block attributes before
     * rendering but does not sanitize them (e.g. "5" is a valid integer), so
     * each attribute of the class is sanitized against its schema first.
     * Attributes of the class that are missing use the constructor default;
     * attributes of block supports (className, …) are ignored.
     *
     * @template T of object
     * @param class-string<T>                     $class
     * @param array<string, mixed>                $attributes
     * @param array<string, array<string, mixed>> $schemas    Attribute schemas of the block type.
     * @return T
     */
    public function createAttributes(string $class, array $attributes, array $schemas): object
    {
        $data = [];
        foreach (array_keys($this->fields($class)) as $name) {
            if (array_key_exists($name, $attributes)) {
                $data[$name] = rest_sanitize_value_from_schema($attributes[$name], $schemas[$name], $name);
            }
        }

        return $this->createObject($class, $data, '');
    }

    /**
     * Converts a value according to its description.
     *
     * @param Value  $value
     * @param string $path  Location of the value for error messages, e.g. "buttons[1].url".
     */
    public function create(array $value, mixed $raw, string $path): mixed
    {
        if ($raw === null) {
            if ($value['nullable']) {
                return null;
            }
            throw self::mismatch($path, $value, $raw);
        }

        return match ($value['type']) {
            'int' => is_int($raw)
                ? (isset($value['reference']) ? $this->resolve($value['reference'], $raw) : $raw)
                : throw self::mismatch($path, $value, $raw),
            // JSON does not distinguish 1 from 1.0.
            'float' => is_float($raw) || is_int($raw) ? (float) $raw : throw self::mismatch($path, $value, $raw),
            'string' => is_string($raw) ? $raw : throw self::mismatch($path, $value, $raw),
            'bool' => is_bool($raw) ? $raw : throw self::mismatch($path, $value, $raw),
            'enum' => $this->createEnum($value['class'], $raw, $path),
            'object' => match (true) {
                !is_array($raw) => throw self::mismatch($path, $value, $raw),
                $value['class'] === Link::class => $this->resolveLink($this->createObject(Link::class, $raw, $path), $path),
                default => $this->createObject($value['class'], $raw, $path),
            },
            'list' => is_array($raw) && array_is_list($raw)
                ? $this->createList($value['items'], $raw, $path)
                : throw self::mismatch($path, $value, $raw),
        };
    }

    /**
     * @param ClassValue|ReferenceValue $items
     * @param list<mixed>               $raw
     * @return list<mixed>
     */
    private function createList(array $items, array $raw, string $path): array
    {
        $list = [];
        foreach ($raw as $index => $item) {
            $value = $this->create($items, $item, sprintf('%s[%d]', $path, $index));
            // A reference that does not resolve is left out.
            if ($value !== null || $items['type'] !== 'int') {
                $list[] = $value;
            }
        }

        return $list;
    }

    /**
     * The ID (the post object for a post class) if the referenced object exists and may be used here, else null.
     *
     * @param Reference $reference
     */
    private function resolve(array $reference, int $id): int|AbstractPost|null
    {
        $editor = $this->context?->isEditor() ?? false;
        if (isset($reference['class'])) {
            $post = get_post($id);
            if (!$post instanceof WP_Post || !in_array($post->post_type, $reference['subtypes'] ?? [], true)) {
                return null;
            }

            return $editor || is_post_status_viewable($post->post_status) ? $reference['class']::fromWpPost($post) : null;
        }
        $subtypes = $reference['subtypes'];
        $valid = match ($reference['target']) {
            'post' => ($post = get_post($id)) instanceof WP_Post
                && ($subtypes === null ? is_post_type_viewable($post->post_type) : in_array($post->post_type, $subtypes, true))
                && ($editor || is_post_publicly_viewable($post)),
            'term' => ($term = get_term($id)) instanceof WP_Term
                && ($subtypes === null ? is_taxonomy_viewable($term->taxonomy) : in_array($term->taxonomy, $subtypes, true)),
            'user' => get_userdata($id) instanceof WP_User,
        };

        return $valid ? $id : null;
    }

    /**
     * @template T of object
     * @param class-string<T>      $class
     * @param array<mixed, mixed>  $data
     * @return T
     */
    private function createObject(string $class, array $data, string $path): object
    {
        $named = [];
        foreach ($this->fields($class) as $name => $field) {
            $fieldPath = $path === '' ? $name : $path . '.' . $name;
            if (!array_key_exists($name, $data)) {
                if ($field['required']) {
                    throw new InvalidArgumentException(sprintf('Value "%s" for %s::$%s is missing.', $fieldPath, $class, $name));
                }
                continue;
            }
            $named[$name] = $this->create($field['value'], $data[$name], $fieldPath);
        }

        return new $class(...$named);
    }

    /**
     * The link with the current URL of its linked object; an empty URL if the object does not exist or may not be
     * shown here. Free URLs stay as they are.
     */
    private function resolveLink(Link $link, string $path): Link
    {
        if (($link->kind === LinkKind::Url) !== ($link->id === 0) || $link->id < 0) {
            throw new InvalidArgumentException(sprintf(
                'Value "%s" is an invalid link: kind "%s" with ID %d; a free URL has the ID 0, a linked post, term or attachment its positive ID.',
                $path,
                $link->kind->value,
                $link->id,
            ));
        }
        if ($link->kind === LinkKind::Url) {
            return $link;
        }

        $editor = $this->context?->isEditor() ?? false;
        $url = match ($link->kind) {
            LinkKind::Post => ($post = get_post($link->id)) instanceof WP_Post
                && is_post_type_viewable($post->post_type)
                && ($editor || is_post_publicly_viewable($post))
                ? get_permalink($post)
                : false,
            LinkKind::Term => ($term = get_term($link->id)) instanceof WP_Term && is_taxonomy_viewable($term->taxonomy)
                ? get_term_link($term)
                : false,
            LinkKind::Attachment => ($post = get_post($link->id)) instanceof WP_Post
                && $post->post_type === 'attachment'
                && ($editor || is_post_publicly_viewable($post))
                ? wp_get_attachment_url($post->ID)
                : false,
        };

        return new Link(is_string($url) ? $url : '', $link->opensInNewTab, $link->kind, $link->id);
    }

    /**
     * @param class-string $class
     */
    private function createEnum(string $class, mixed $raw, string $path): BackedEnum
    {
        /** @var class-string<BackedEnum> $class */
        $case = is_int($raw) || is_string($raw) ? $class::tryFrom($raw) : null;
        if ($case === null) {
            throw new InvalidArgumentException(sprintf('Value "%s" must be a value of %s, %s given.', $path, $class, var_export($raw, true)));
        }

        return $case;
    }

    /**
     * @param class-string $class
     * @return array<string, ValueField>
     */
    private function fields(string $class): array
    {
        return $this->classes[$class] ?? throw new LogicException(sprintf(
            'Value class %s is not in the manifest; rebuild the manifest after code changes.',
            $class,
        ));
    }

    /**
     * @param Value $value
     */
    private static function mismatch(string $path, array $value, mixed $raw): InvalidArgumentException
    {
        $expected = match ($value['type']) {
            'object' => 'an object of ' . $value['class'],
            'list' => 'a list',
            'enum' => 'a value of ' . $value['class'],
            default => $value['type'],
        };

        return new InvalidArgumentException(sprintf(
            'Value "%s" must be %s%s, %s given.',
            $path,
            $expected,
            $value['nullable'] ? ' or null' : '',
            get_debug_type($raw),
        ));
    }
}
