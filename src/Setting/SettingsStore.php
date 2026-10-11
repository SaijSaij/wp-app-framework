<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Setting;

use LogicException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Project\ComposerProject;
use Saij\WpAppFramework\Value\ValueFactory;

/**
 * The settings groups of one framework instance: registers their options
 * (`register_setting()`, editable through /wp/v2/settings) and creates the
 * group objects from the stored values.
 *
 * Saved rich text (`#[RichText]`) of users without `unfiltered_html` is
 * filtered with wp_kses_post(). Stored values are checked per setting against
 * its schema: a value that no longer matches (e.g. a removed enum case) falls
 * back to the default and is logged. Group objects are created on first use
 * and kept until the option changes.
 *
 * @internal
 *
 * @phpstan-import-type SettingsGroupEntry from SettingsCollector
 */
#[NoService]
final class SettingsStore
{
    /** @var array<class-string, SettingsGroupEntry> */
    private array $groups = [];

    /** @var array<class-string, object> */
    private array $objects = [];

    /**
     * @param non-empty-string         $slug
     * @param list<SettingsGroupEntry> $groups
     */
    public function __construct(
        private readonly string $slug,
        array $groups,
        private readonly ValueFactory $values,
    ) {
        foreach ($groups as $group) {
            $this->groups[$group['class']] = $group;
        }
    }

    /**
     * The option (and REST field) of a group: the slug with "_" instead of "-", "_" and the group name.
     *
     * @param non-empty-string $slug
     * @return non-empty-string
     */
    public static function optionName(string $slug, string $name): string
    {
        return ComposerProject::templatePrefix($slug) . $name;
    }

    /**
     * Registers the options on init and forgets group objects when their option changes.
     */
    public function hook(): void
    {
        add_action('init', $this->register(...));
        foreach ($this->groups as $class => $group) {
            $option = self::optionName($this->slug, $group['name']);
            $forget = function () use ($class): void {
                unset($this->objects[$class]);
            };
            // The first save adds the option and fires only add_option_{option} (update_option() → add_option()).
            add_action('add_option_' . $option, $forget);
            add_action('update_option_' . $option, $forget);
            add_action('delete_option_' . $option, $forget);
        }
    }

    /**
     * Registers each group as an option with its schema, shown in the REST API.
     */
    public function register(): void
    {
        foreach ($this->groups as $group) {
            $option = self::optionName($this->slug, $group['name']);
            register_setting($this->slug, $option, [
                'type' => 'object',
                'label' => $group['title'],
                'description' => $group['description'],
                'default' => $group['default'],
                'sanitize_callback' => fn(mixed $value): array => $this->complete($group, $value),
                'show_in_rest' => ['name' => $option, 'schema' => $group['schema']],
            ]);
        }
    }

    /**
     * Group classes by group name.
     *
     * @return array<non-empty-string, class-string>
     */
    public function getClasses(): array
    {
        $classes = [];
        foreach ($this->groups as $class => $group) {
            $classes[$group['name']] = $class;
        }

        return $classes;
    }

    /**
     * The group object with the stored values.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function get(string $class): object
    {
        if (isset($this->objects[$class])) {
            /** @var T Objects are stored under their class name. */
            return $this->objects[$class];
        }
        $group = $this->groups[$class] ?? throw new LogicException(sprintf(
            '%s is no settings group of the %s instance; rebuild the manifest after code changes.',
            $class,
            $this->slug,
        ));

        $option = self::optionName($this->slug, $group['name']);
        // False until the option is saved and before register() added its default.
        $stored = get_option($option);
        if ($stored !== false && !is_array($stored)) {
            self::log(sprintf('the option %s holds %s instead of an object; all settings of the group use their defaults.', $option, get_debug_type($stored)));
        }
        $raw = is_array($stored) ? $stored : [];

        $data = [];
        foreach ($group['schema']['properties'] as $name => $schema) {
            if (!array_key_exists($name, $raw)) {
                continue;
            }
            $valid = rest_validate_value_from_schema($raw[$name], $schema, $option . '[' . $name . ']');
            if (is_wp_error($valid)) {
                self::log(sprintf('the stored value of %s[%s] is invalid (%s); the setting uses its default.', $option, $name, $valid->get_error_message()));
                continue;
            }
            $data[$name] = rest_sanitize_value_from_schema($raw[$name], $schema, $option . '[' . $name . ']');
        }

        /** @var T $object Created by the value factory from the class's own value description. */
        $object = $this->values->create(['type' => 'object', 'class' => $class, 'nullable' => false], $data, $option);

        return $this->objects[$class] = $object;
    }

    /**
     * The value to store: settings missing in a saved object get their default,
     * so the option always holds every setting (the REST API also accepts
     * objects with only some of them).
     *
     * @param SettingsGroupEntry $group
     * @return array<string, mixed>
     */
    private function complete(array $group, mixed $value): array
    {
        if (!is_array($value)) {
            self::log(sprintf(
                'the option %s was saved with %s instead of an object; the defaults are stored instead.',
                self::optionName($this->slug, $group['name']),
                get_debug_type($value),
            ));

            return $group['default'];
        }

        /** @var array<string, mixed> $value */
        $value += $group['default'];
        // Like WordPress for block attributes (filter_block_kses()): HTML of users without unfiltered_html is filtered.
        if (!current_user_can('unfiltered_html')) {
            foreach ($group['richText'] as $path) {
                /** @var array<string, mixed> $value filterHtml() keeps the shape of an array. */
                $value = self::filterHtml($value, explode('.', $path));
            }
        }

        return $value;
    }

    /**
     * Filters the rich text at a path ("footer", "links.*.text") with wp_kses_post().
     *
     * @param list<string> $path
     */
    private static function filterHtml(mixed $value, array $path): mixed
    {
        if ($path === []) {
            return is_string($value) ? wp_kses_post($value) : $value;
        }
        if (!is_array($value)) {
            return $value;
        }
        $segment = array_shift($path);
        foreach ($value as $key => $item) {
            if ($segment === '*' ? is_int($key) : $key === $segment) {
                $value[$key] = self::filterHtml($item, $path);
            }
        }

        return $value;
    }

    private static function log(string $message): void
    {
        error_log('wp-app-framework: settings: ' . $message);
    }
}
