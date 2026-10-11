<?php

declare(strict_types=1);

/*
 * Minimal test doubles of the WordPress classes and functions used by the
 * framework's runtime code. They implement only what the framework calls, with
 * the signatures of WordPress 7.1 (static analysis checks the tests against the
 * real ones from php-stubs/wordpress-stubs); WordPress is not loaded in unit tests.
 * Public properties mirror the public properties of the WordPress classes.
 */

final class WP_Error
{
    public function __construct(
        private string $code = '',
        private string $message = '',
        private mixed $data = '',
    ) {}

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }

    public function get_error_data(): mixed
    {
        return $this->data;
    }
}

final class WP_REST_Response
{
    public function __construct(
        private mixed $data = null,
        private int $status = 200,
    ) {}

    public function get_data(): mixed
    {
        return $this->data;
    }

    public function get_status(): int
    {
        return $this->status;
    }
}

/**
 * Query and URL parameters only; like WordPress (GET before URL), query parameters take
 * precedence in get_param(), which skips null values (isset).
 */
final class WP_REST_Request
{
    /** @var array<string, mixed> */
    private array $queryParams = [];

    /** @var array<string, mixed> */
    private array $urlParams = [];

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(string $method = '', string $route = '', array $attributes = []) {}

    /**
     * @param array<string, mixed> $params
     */
    public function set_query_params(array $params): void
    {
        $this->queryParams = $params;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function set_url_params(array $params): void
    {
        $this->urlParams = $params;
    }

    public function has_param(string $key): bool
    {
        return array_key_exists($key, $this->queryParams) || array_key_exists($key, $this->urlParams);
    }

    public function get_param(string $key): mixed
    {
        return $this->queryParams[$key] ?? $this->urlParams[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function get_url_params(): array
    {
        return $this->urlParams;
    }
}

function is_wp_error(mixed $thing): bool
{
    return $thing instanceof WP_Error;
}

/**
 * Simplified: checks the integer and string types, enums (and null for nullable types).
 *
 * @param array<string, mixed> $args
 */
function rest_validate_value_from_schema(mixed $value, array $args, string $param = ''): true|WP_Error
{
    $types = (array) $args['type'];
    if (in_array('object', $types, true) && ($args['additionalProperties'] ?? true) === false && is_array($value)) {
        foreach (array_keys($value) as $key) {
            if (!isset($args['properties'][$key])) {
                return new WP_Error('rest_additional_properties_forbidden', sprintf('%s is not a valid property of Object.', $key));
            }
        }
    }
    if ($value === null && in_array('null', $types, true)) {
        return true;
    }
    if (in_array('integer', $types, true) && !is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
        return new WP_Error('rest_invalid_type', sprintf('%s is not of type integer.', $param));
    }
    if ($types === ['string'] && !is_string($value)) {
        return new WP_Error('rest_invalid_type', sprintf('%s is not of type string.', $param));
    }
    if (isset($args['enum']) && is_array($args['enum']) && !in_array($value, $args['enum'], true)) {
        return new WP_Error('rest_not_in_enum', sprintf('%s is not one of %s.', $param, implode(', ', array_map(strval(...), array_filter($args['enum'], is_scalar(...))))));
    }

    return true;
}

/**
 * Simplified: casts only integers.
 *
 * @param array<string, mixed> $args
 */
function rest_sanitize_value_from_schema(mixed $value, array $args, string $param = ''): mixed
{
    return $value !== null && in_array('integer', (array) $args['type'], true) ? (int) $value : $value;
}

/**
 * A parsed block; render() calls the render callback of its registered type with the instance, like WordPress.
 */
final class WP_Block
{
    public string $name;

    /** @var array<string, mixed> */
    public array $attributes;

    public string $inner_html;

    /**
     * @param array{blockName: string, attrs?: array<string, mixed>, innerHTML?: string} $block
     * @param array<string, mixed>                                                      $available_context
     */
    public function __construct(array $block, array $available_context = [], ?WP_Block_Type_Registry $registry = null)
    {
        $this->name = $block['blockName'];
        $this->attributes = $block['attrs'] ?? [];
        $this->inner_html = $block['innerHTML'] ?? '';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function render(array $options = []): string
    {
        $type = WP_Block_Type_Registry::get_instance()->get_registered($this->name);
        if ($type === null || $type->render_callback === null) {
            return '';
        }

        // Like WordPress for a block without inner blocks: the block content is the inner HTML.
        return (string) ($type->render_callback)($this->attributes, $this->inner_html, $this);
    }
}

/**
 * Records the registered block types in $GLOBALS['wp_app_framework_test_blocks'] (not in the registry).
 *
 * @param array<string, mixed> $args
 */
function register_block_type(string $block_type, array $args = []): WP_Block_Type|false
{
    $GLOBALS['wp_app_framework_test_blocks'][$block_type] = $args;

    return new WP_Block_Type($block_type, $args);
}

/**
 * @param array<string, string> $extra
 */
function get_block_wrapper_attributes(array $extra = []): string
{
    return 'class="wp-block-test' . (isset($extra['class']) ? ' ' . $extra['class'] : '') . '"';
}

/**
 * @param array<string, string> $attr
 */
function wp_get_attachment_image(int $attachment_id, string $size = 'thumbnail', bool $icon = false, array $attr = []): string
{
    return sprintf('<img data-id="%d" data-size="%s">', $attachment_id, $size);
}

/**
 * Registered block types with their registration arguments; render_block() calls their render callback.
 */
final class WP_Block_Type_Registry
{
    private static ?self $instance = null;

    /** @var array<string, WP_Block_Type> */
    private array $types = [];

    public static function get_instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @param array<string, mixed> $args
     */
    public function register(string $name, array $args = []): WP_Block_Type|false
    {
        return $this->types[$name] = new WP_Block_Type($name, $args);
    }

    public function is_registered(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function unregister(string $name): WP_Block_Type|false
    {
        $type = $this->types[$name] ?? false;
        unset($this->types[$name]);

        return $type;
    }

    public function get_registered(string $name): ?WP_Block_Type
    {
        return $this->types[$name] ?? null;
    }

    /**
     * @return array<string, WP_Block_Type>
     */
    public function get_all_registered(): array
    {
        return $this->types;
    }
}

final class WP_Block_Type
{
    public string $name;

    /** @var array<string, array<string, mixed>>|null */
    public ?array $attributes;

    /** @var (callable(array<string, mixed>, string, WP_Block=): string)|null */
    public $render_callback;

    /**
     * @param array<string, mixed> $args
     */
    public function __construct(string $block_type, array $args = [])
    {
        $this->name = $block_type;
        $this->attributes = $args['attributes'] ?? null;
        $this->render_callback = $args['render_callback'] ?? null;
    }

    /**
     * With the attributes WordPress adds to every block type (lock, metadata).
     *
     * @return array<string, array<string, mixed>>
     */
    public function get_attributes(): array
    {
        return ($this->attributes ?? []) + ['lock' => ['type' => 'object'], 'metadata' => ['type' => 'object']];
    }
}

/**
 * @param array{blockName: string, attrs: array<string, mixed>, innerHTML?: string} $parsed_block
 */
function render_block(array $parsed_block, ?WP_Block $parent_block = null): string
{
    return new WP_Block($parsed_block)->render();
}

/**
 * Capabilities of the current user: $GLOBALS['wp_app_framework_test_caps'] lists the granted ones,
 * e.g. "edit_posts" or "edit_post:7".
 */
function current_user_can(string $capability, mixed ...$args): bool
{
    $name = $args === [] ? $capability : $capability . ':' . implode(':', $args);

    return in_array($name, $GLOBALS['wp_app_framework_test_caps'] ?? [], true);
}

/**
 * Posts from $GLOBALS['wp_app_framework_test_posts'] by ID.
 */
function get_post(int|WP_Post|null $post = null, string $output = 'OBJECT', string $filter = 'raw'): ?WP_Post
{
    if ($post instanceof WP_Post) {
        return $post;
    }

    return $GLOBALS['wp_app_framework_test_posts'][(int) $post] ?? null;
}

function setup_postdata(object $post): bool
{
    $GLOBALS['wp_app_framework_test_postdata'] = $post->ID;

    return true;
}

function esc_attr(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES);
}

function wp_json_encode(mixed $value, int $flags = 0): string|false
{
    return json_encode($value, $flags);
}

/**
 * Records registered scripts in $GLOBALS['wp_app_framework_test_scripts'].
 *
 * @param list<string>         $deps
 * @param array<string, mixed> $args
 */
function wp_register_script(string $handle, string $src, array $deps = [], string|bool|null $ver = false, array|bool $args = []): bool
{
    $GLOBALS['wp_app_framework_test_scripts'][$handle] = ['src' => $src, 'deps' => $deps, 'ver' => $ver, 'enqueued' => false, 'before' => []];

    return true;
}

function wp_add_inline_script(string $handle, string $data, string $position = 'after'): bool
{
    $GLOBALS['wp_app_framework_test_scripts'][$handle][$position][] = $data;

    return true;
}

function wp_enqueue_script(string $handle): void
{
    $GLOBALS['wp_app_framework_test_scripts'][$handle]['enqueued'] = true;
}

/**
 * Records registered styles in $GLOBALS['wp_app_framework_test_styles'].
 *
 * @param list<string> $deps
 */
function wp_register_style(string $handle, string $src, array $deps = [], string|bool|null $ver = false): bool
{
    $GLOBALS['wp_app_framework_test_styles'][$handle] = ['src' => $src, 'deps' => $deps, 'ver' => $ver, 'enqueued' => false];

    return true;
}

function wp_enqueue_style(string $handle): void
{
    $GLOBALS['wp_app_framework_test_styles'][$handle]['enqueued'] = true;
}

function wp_style_add_data(string $handle, string $key, mixed $value): bool
{
    $GLOBALS['wp_app_framework_test_styles'][$handle][$key] = $value;

    return true;
}

/**
 * True if $GLOBALS['wp_app_framework_test_admin'] is set.
 */
function is_admin(): bool
{
    return $GLOBALS['wp_app_framework_test_admin'] ?? false;
}

/**
 * Like WordPress: true on a block editor screen ($GLOBALS['wp_app_framework_test_block_editor_screen']), filtered by
 * should_load_block_editor_scripts_and_styles.
 */
function wp_should_load_block_editor_scripts_and_styles(): bool
{
    return (bool) apply_filters('should_load_block_editor_scripts_and_styles', $GLOBALS['wp_app_framework_test_block_editor_screen'] ?? false);
}

/**
 * Records filters and actions in $GLOBALS['wp_app_framework_test_filters'][$hook_name]; apply_filters() and
 * do_action() call them in the order added (priorities are ignored).
 */
function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): true
{
    $GLOBALS['wp_app_framework_test_filters'][$hook_name][] = $callback;
    $GLOBALS['wp_app_framework_test_filter_args'][$hook_name][] = $accepted_args;

    return true;
}

function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): true
{
    return add_filter($hook_name, $callback, $priority, $accepted_args);
}

function apply_filters(string $hook_name, mixed $value, mixed ...$args): mixed
{
    foreach ($GLOBALS['wp_app_framework_test_filters'][$hook_name] ?? [] as $index => $callback) {
        $accepted = $GLOBALS['wp_app_framework_test_filter_args'][$hook_name][$index] ?? 1;
        $value = $callback(...array_slice([$value, ...$args], 0, $accepted));
    }

    return $value;
}

/**
 * Counts actions in $GLOBALS['wp_app_framework_test_actions'][$hook_name] and calls their callbacks.
 */
function do_action(string $hook_name, mixed ...$arg): void
{
    $GLOBALS['wp_app_framework_test_actions'][$hook_name] = ($GLOBALS['wp_app_framework_test_actions'][$hook_name] ?? 0) + 1;
    foreach ($GLOBALS['wp_app_framework_test_filters'][$hook_name] ?? [] as $index => $callback) {
        $accepted = $GLOBALS['wp_app_framework_test_filter_args'][$hook_name][$index] ?? 1;
        $callback(...array_slice($arg, 0, $accepted));
    }
}

function did_action(string $hook_name): int
{
    return $GLOBALS['wp_app_framework_test_actions'][$hook_name] ?? 0;
}

/**
 * Whether $GLOBALS['wp_app_framework_test_doing_filter'] names the filter.
 */
function doing_filter(?string $hook_name = null): bool
{
    return ($GLOBALS['wp_app_framework_test_doing_filter'] ?? null) === $hook_name;
}

function esc_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function sanitize_html_class(string $classname, string $fallback = ''): string
{
    $sanitized = (string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) preg_replace('|%[a-fA-F0-9][a-fA-F0-9]|', '', $classname));
    if ($sanitized === '' && $fallback !== '') {
        return sanitize_html_class($fallback);
    }

    return $sanitized;
}

/**
 * The active themes from $GLOBALS['wp_app_framework_test_themes']:
 * `['template' => slug, 'stylesheet' => slug, 'root' => theme root, 'versions' => [slug => version]]`.
 *
 * @return array{template: string, stylesheet: string, root: string, versions: array<string, string>}
 */
function wp_app_framework_test_themes(): array
{
    return $GLOBALS['wp_app_framework_test_themes'] ?? throw new LogicException('The test sets no active themes.');
}

function get_template(): string
{
    return wp_app_framework_test_themes()['template'];
}

function get_stylesheet(): string
{
    return wp_app_framework_test_themes()['stylesheet'];
}

function get_template_directory(): string
{
    return wp_app_framework_test_themes()['root'] . '/' . get_template();
}

function get_stylesheet_directory(): string
{
    return wp_app_framework_test_themes()['root'] . '/' . get_stylesheet();
}

function get_template_directory_uri(): string
{
    return 'https://example.test/wp-content/themes/' . get_template();
}

function get_stylesheet_directory_uri(): string
{
    return 'https://example.test/wp-content/themes/' . get_stylesheet();
}

final class WP_Theme
{
    public function __construct(
        private readonly string $stylesheet,
    ) {}

    /**
     * Only the header "Version" is known.
     *
     * @return string|array<string>|false
     */
    public function get(string $header): string|array|false
    {
        return $header === 'Version' ? wp_app_framework_test_themes()['versions'][$this->stylesheet] ?? false : false;
    }
}

function wp_get_theme(string $stylesheet = '', string $theme_root = ''): WP_Theme
{
    return new WP_Theme($stylesheet === '' ? get_stylesheet() : $stylesheet);
}

// The content directory of the unit tests; caches of booted instances go below it.
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/wpaf-wp-content');

/**
 * Records registered settings in $GLOBALS['wp_app_framework_test_settings'] by option name.
 *
 * @param array<string, mixed> $args
 */
function register_setting(string $option_group, string $option_name, array $args = []): void
{
    $GLOBALS['wp_app_framework_test_settings'][$option_name] = ['group' => $option_group] + $args;
}

/**
 * Options from $GLOBALS['wp_app_framework_test_options'].
 */
function get_option(string $option, mixed $default_value = false): mixed
{
    return array_key_exists($option, $GLOBALS['wp_app_framework_test_options'] ?? [])
        ? $GLOBALS['wp_app_framework_test_options'][$option]
        : $default_value;
}

/**
 * Records admin pages in $GLOBALS['wp_app_framework_test_pages'] by menu slug; returns the hook suffix like WordPress.
 */
function add_theme_page(string $page_title, string $menu_title, string $capability, string $menu_slug, callable|string $callback = '', ?int $position = null): string|false
{
    $GLOBALS['wp_app_framework_test_pages'][$menu_slug] = ['parent' => 'themes.php', 'title' => $page_title, 'menu' => $menu_title, 'capability' => $capability, 'callback' => $callback];

    return 'appearance_page_' . $menu_slug;
}

function add_options_page(string $page_title, string $menu_title, string $capability, string $menu_slug, callable|string $callback = '', ?int $position = null): string|false
{
    $GLOBALS['wp_app_framework_test_pages'][$menu_slug] = ['parent' => 'options-general.php', 'title' => $page_title, 'menu' => $menu_title, 'capability' => $capability, 'callback' => $callback];

    return 'settings_page_' . $menu_slug;
}

/**
 * Counts calls in $GLOBALS['wp_app_framework_test_media'].
 *
 * @param array<string, mixed> $args
 */
function wp_enqueue_media(array $args = []): void
{
    $GLOBALS['wp_app_framework_test_media'] = ($GLOBALS['wp_app_framework_test_media'] ?? 0) + 1;
}

/**
 * Like WordPress: the properties come from the given object.
 */
final class WP_Post
{
    public int $ID = 0;
    public string $post_type = 'post';
    public string $post_status = 'publish';
    public string $post_title = '';

    public function __construct(object $post)
    {
        foreach (get_object_vars($post) as $key => $value) {
            $this->$key = $value;
        }
    }
}

/**
 * Like WordPress: the properties come from the given object.
 */
final class WP_Term
{
    public int $term_id = 0;
    public string $taxonomy = 'category';
    public string $name = '';

    public function __construct(object $term)
    {
        foreach (get_object_vars($term) as $key => $value) {
            $this->$key = $value;
        }
    }
}

/**
 * Like WordPress for a user data object: the properties come from it.
 */
final class WP_User
{
    public int $ID = 0;
    public string $display_name = '';

    public function __construct(int|object $id = 0, string $name = '', string $site_id = '')
    {
        if (is_object($id)) {
            foreach (get_object_vars($id) as $key => $value) {
                $this->$key = $value;
            }
        } else {
            $this->ID = $id;
        }
    }
}

/**
 * Terms from $GLOBALS['wp_app_framework_test_terms'] by ID.
 */
function get_term(int|object $term, string $taxonomy = '', string $output = 'OBJECT', string $filter = 'raw'): WP_Term|WP_Error|null
{
    return $term instanceof WP_Term ? $term : $GLOBALS['wp_app_framework_test_terms'][is_int($term) ? $term : 0] ?? null;
}

/**
 * Users from $GLOBALS['wp_app_framework_test_users'] by ID.
 */
function get_userdata(int $user_id): WP_User|false
{
    return $GLOBALS['wp_app_framework_test_users'][$user_id] ?? false;
}

/**
 * Viewable post types: post and page (like a default installation).
 */
function is_post_type_viewable(string|object $post_type): bool
{
    return in_array($post_type, ['post', 'page'], true);
}

/**
 * Published posts of viewable post types.
 */
function is_post_publicly_viewable(int|WP_Post|null $post = null): bool
{
    $post = get_post($post);

    return $post !== null && $post->post_status === 'publish' && is_post_type_viewable($post->post_type);
}

/**
 * Viewable taxonomies: category and post_tag.
 */
function is_taxonomy_viewable(string|object $taxonomy): bool
{
    return in_array($taxonomy, ['category', 'post_tag'], true);
}

function get_permalink(int|WP_Post $post = 0, bool $leavename = false): string|false
{
    $post = get_post($post);

    return $post === null ? false : 'https://example.test/?p=' . $post->ID;
}

function get_term_link(WP_Term|int|string $term, string $taxonomy = ''): string|WP_Error
{
    return $term instanceof WP_Term ? 'https://example.test/?cat=' . $term->term_id : new WP_Error('invalid_term', 'Empty Term.');
}

function wp_specialchars_decode(string $text, int|string $quote_style = ENT_NOQUOTES): string
{
    return htmlspecialchars_decode($text, ENT_QUOTES);
}

/**
 * Simplified: removes script elements.
 */
function wp_kses_post(string $data): string
{
    return (string) preg_replace('~<script\b[^>]*>.*?</script>~is', '', $data);
}

/**
 * Counts calls in $GLOBALS['wp_app_framework_test_editor'].
 */
function wp_enqueue_editor(): void
{
    $GLOBALS['wp_app_framework_test_editor'] = ($GLOBALS['wp_app_framework_test_editor'] ?? 0) + 1;
}
