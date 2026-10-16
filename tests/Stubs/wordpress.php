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
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private mixed $data = null,
        private int $status = 200,
        private array $headers = [],
    ) {}

    /**
     * @return array<string, string>
     */
    public function get_headers(): array
    {
        return $this->headers;
    }

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

    private string $method;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(string $method = '', string $route = '', array $attributes = [])
    {
        $this->method = strtoupper($method);
    }

    public function get_method(): string
    {
        return $this->method;
    }

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

    /** @var array<string, mixed> Simplified: all available context (WordPress keeps only the block type's uses_context). */
    public array $context = [];

    /** @var array<string, mixed> */
    public array $parsed_block;

    public ?WP_Block_Type $block_type = null;

    /** @var array<string, mixed> Like WordPress: protected. */
    protected array $available_context = [];

    /**
     * @param array{blockName: string, attrs?: array<string, mixed>, innerHTML?: string} $block
     * @param array<string, mixed>                                                      $available_context
     */
    public function __construct(array $block, array $available_context = [], ?WP_Block_Type_Registry $registry = null)
    {
        $this->name = $block['blockName'];
        $this->attributes = $block['attrs'] ?? [];
        $this->inner_html = $block['innerHTML'] ?? '';
        $this->context = $available_context;
        $this->available_context = $available_context;
        $this->parsed_block = $block;
        $this->block_type = WP_Block_Type_Registry::get_instance()->get_registered($this->name);
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
 * The class of a test block plus the extra attributes; attributes other than class, style, id and
 * aria-label are appended escaped, as in WordPress (class-wp-block-supports.php).
 *
 * @param array<string, string> $extra
 */
function get_block_wrapper_attributes(array $extra = []): string
{
    $attributes = ['class="wp-block-test' . (isset($extra['class']) ? ' ' . $extra['class'] : '') . '"'];
    foreach ($extra as $name => $value) {
        if (!in_array($name, ['class', 'style', 'id', 'aria-label'], true)) {
            $attributes[] = $name . '="' . esc_attr($value) . '"';
        }
    }

    return implode(' ', $attributes);
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

    /** @var string[] */
    private array $uses_context;

    /**
     * @param array<string, mixed> $args
     */
    public function __construct(string $block_type, array $args = [])
    {
        $this->name = $block_type;
        $this->attributes = $args['attributes'] ?? null;
        $this->render_callback = $args['render_callback'] ?? null;
        $this->uses_context = $args['uses_context'] ?? [];
    }

    /**
     * @return string[]
     */
    public function get_uses_context(): array
    {
        return $this->uses_context;
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
/**
 * Parsed blocks prepared by a test in $GLOBALS['wp_app_framework_test_parsed_blocks'] by content.
 *
 * @return list<array{blockName: string|null, attrs: array<string, mixed>, innerBlocks: list<array<string, mixed>>, innerHTML: string, innerContent: list<string|null>}>
 */
function parse_blocks(string $content): array
{
    return $GLOBALS['wp_app_framework_test_parsed_blocks'][$content] ?? [];
}

function render_block(array $parsed_block, ?WP_Block $parent_block = null): string
{
    /** @var array<string, mixed> $context Like WordPress: the default context passes the filter render_block_context. */
    $context = apply_filters('render_block_context', [], $parsed_block, $parent_block);

    return new WP_Block($parsed_block, $context)->render();
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
    // Like WordPress: without an argument, the global post.
    if ($post === null) {
        return ($GLOBALS['post'] ?? null) instanceof WP_Post ? $GLOBALS['post'] : null;
    }

    return $GLOBALS['wp_app_framework_test_posts'][$post] ?? null;
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
    $GLOBALS['wp_app_framework_test_scripts'][$handle] = ['src' => $src, 'deps' => $deps, 'ver' => $ver, 'args' => $args, 'enqueued' => false, 'before' => []];

    return true;
}

/**
 * Status of a script recorded in $GLOBALS['wp_app_framework_test_scripts'] ("registered" or "enqueued").
 */
function wp_script_is(string $handle, string $status = 'enqueued'): bool
{
    $script = $GLOBALS['wp_app_framework_test_scripts'][$handle] ?? null;

    return match ($status) {
        'registered' => $script !== null,
        'enqueued' => (bool) ($script['enqueued'] ?? false),
        default => false,
    };
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
    $GLOBALS['wp_app_framework_test_current_actions'][] = $hook_name;
    try {
        foreach ($GLOBALS['wp_app_framework_test_filters'][$hook_name] ?? [] as $index => $callback) {
            $accepted = $GLOBALS['wp_app_framework_test_filter_args'][$hook_name][$index] ?? 1;
            $callback(...array_slice($arg, 0, $accepted));
        }
    } finally {
        array_pop($GLOBALS['wp_app_framework_test_current_actions']);
    }
}

/**
 * The innermost action do_action() runs.
 */
function current_action(): string|false
{
    $actions = $GLOBALS['wp_app_framework_test_current_actions'] ?? [];

    return $actions === [] ? false : $actions[array_key_last($actions)];
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
    /**
     * @param string|null $template The parent theme of a child theme.
     */
    public function __construct(
        private readonly string $stylesheet,
        private readonly ?string $template = null,
    ) {}

    public function get_stylesheet(): string
    {
        return $this->stylesheet;
    }

    public function get_template(): string
    {
        return $this->template ?? $this->stylesheet;
    }

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

/**
 * The parent theme of a child theme comes from $GLOBALS['wp_app_framework_test_theme_parents'][$stylesheet].
 */
function wp_get_theme(string $stylesheet = '', string $theme_root = ''): WP_Theme
{
    $stylesheet = $stylesheet === '' ? get_stylesheet() : $stylesheet;

    return new WP_Theme($stylesheet, $GLOBALS['wp_app_framework_test_theme_parents'][$stylesheet] ?? null);
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
 * Options from $GLOBALS['wp_app_framework_test_options']. Reading a missing option, which WordPress answers with a
 * database query without a persistent object cache, is recorded in $GLOBALS['wp_app_framework_test_option_misses'].
 */
function get_option(string $option, mixed $default_value = false): mixed
{
    if (!array_key_exists($option, $GLOBALS['wp_app_framework_test_options'] ?? [])) {
        $GLOBALS['wp_app_framework_test_option_misses'][] = $option;

        return $default_value;
    }

    return $GLOBALS['wp_app_framework_test_options'][$option];
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
 * The settings of theme.json at a path, from `$GLOBALS['wp_app_framework_test_global_settings']` (by path joined with
 * "."); null for others.
 *
 * @param list<string>         $path
 * @param array<string, mixed> $context
 */
function wp_get_global_settings(array $path = [], array $context = []): mixed
{
    return $GLOBALS['wp_app_framework_test_global_settings'][implode('.', $path)] ?? null;
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
    public string $post_excerpt = '';
    public string $post_content = '';
    public string $post_name = '';
    public string $post_author = '0';
    public int $post_parent = 0;
    public string $post_date = '0000-00-00 00:00:00';
    public string $post_date_gmt = '0000-00-00 00:00:00';
    public string $post_modified_gmt = '0000-00-00 00:00:00';

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
    /** @var list<string> */
    public array $roles = [];

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
 * Like WordPress: the properties come from the given object; the ID is a numeric string.
 */
final class WP_Comment
{
    public string $comment_ID = '0';
    public string $comment_post_ID = '0';
    public string $comment_content = '';

    public function __construct(object $comment)
    {
        foreach (get_object_vars($comment) as $key => $value) {
            $this->$key = $value;
        }
    }
}

/**
 * Comments from $GLOBALS['wp_app_framework_test_comments'] by ID.
 */
function get_comment(int|WP_Comment|string|null $comment = null, string $output = 'OBJECT'): WP_Comment|array|null
{
    return $comment instanceof WP_Comment ? $comment : $GLOBALS['wp_app_framework_test_comments'][(int) $comment] ?? null;
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
 * Viewable post types: post, page and attachment (like a default installation).
 */
function is_post_type_viewable(string|object $post_type): bool
{
    return in_array($post_type, ['post', 'page', 'attachment'], true);
}

/**
 * Published posts of viewable post types. Like get_post_status(), an attachment with the status "inherit" has the
 * status of its parent, without parent "publish".
 */
function is_post_publicly_viewable(int|WP_Post|null $post = null): bool
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $status = $post->post_status;
    if ($post->post_type === 'attachment' && $status === 'inherit') {
        $status = $post->post_parent === 0 ? 'publish' : (get_post($post->post_parent)?->post_status ?? 'publish');
    }

    return $status === 'publish' && is_post_type_viewable($post->post_type);
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

/**
 * Terms whose ID is in $GLOBALS['wp_app_framework_test_term_link_errors'] fail like a term of a taxonomy that is not
 * registered.
 */
function get_term_link(WP_Term|int|string $term, string $taxonomy = ''): string|WP_Error
{
    if ($term instanceof WP_Term && in_array($term->term_id, $GLOBALS['wp_app_framework_test_term_link_errors'] ?? [], true)) {
        return new WP_Error('invalid_taxonomy', 'Invalid taxonomy.');
    }

    return $term instanceof WP_Term ? 'https://example.test/?cat=' . $term->term_id : new WP_Error('invalid_term', 'Empty Term.');
}

/**
 * Simplified: an empty string for disallowed protocols, removes characters URLs may not contain and encodes "&"
 * and "'" like WordPress for display.
 *
 * @param list<string>|null $protocols
 */
function esc_url(string $url, ?array $protocols = null, string $_context = 'display'): string
{
    if ($url === '' || preg_match('~^\s*(javascript|data|vbscript):~i', $url) === 1) {
        return '';
    }
    $url = (string) preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i', '', $url);

    return str_replace(['&', "'"], ['&#038;', '&#039;'], $url);
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

function wp_get_attachment_image_url(int $attachment_id, string|array $size = 'thumbnail', bool $icon = false): string|false
{
    return isset($GLOBALS['wp_app_framework_test_posts'][$attachment_id]) ? sprintf('https://example.test/%d-%s.jpg', $attachment_id, is_string($size) ? $size : 'custom') : false;
}

function wp_get_attachment_url(int $attachment_id = 0): string|false
{
    return isset($GLOBALS['wp_app_framework_test_posts'][$attachment_id]) ? sprintf('https://example.test/%d.jpg', $attachment_id) : false;
}

/**
 * Like WordPress: the excerpt of an attachment, false for other posts.
 */
function wp_get_attachment_caption(int $post_id = 0): string|false
{
    $post = get_post($post_id);

    return $post === null || $post->post_type !== 'attachment' ? false : $post->post_excerpt;
}

/**
 * Meta from $GLOBALS['wp_app_framework_test_meta'] by object ID and key.
 */
function get_post_meta(int $post_id, string $key = '', bool $single = false): mixed
{
    return $GLOBALS['wp_app_framework_test_meta'][$post_id][$key] ?? ($single ? '' : []);
}

/**
 * Simplified: the stored excerpt, like WordPress for a manual one.
 */
function get_the_excerpt(int|WP_Post|null $post = null): string
{
    return get_post($post)->post_excerpt ?? '';
}

function wp_strip_all_tags(string $text, bool $remove_breaks = false): string
{
    return trim(strip_tags($text));
}

/**
 * Simplified: the post date in the given format, Y-m-d by default.
 */
function get_the_date(string $format = '', int|WP_Post|null $post = null): string|int|false
{
    $post = get_post($post);

    return $post === null ? false : date($format === '' ? 'Y-m-d' : $format, (int) strtotime($post->post_date));
}

/**
 * @param array<string, string> $attr
 */
function get_the_post_thumbnail(int|WP_Post|null $post = null, string|array $size = 'post-thumbnail', array|string $attr = ''): string
{
    $post = get_post($post);

    return $post === null ? '' : sprintf('<img data-post="%d" data-size="%s">', $post->ID, is_string($size) ? $size : 'custom');
}

function get_author_posts_url(int $author_id, string $author_nicename = ''): string
{
    return 'https://example.test/?author=' . $author_id;
}

/**
 * Like WordPress with avatars turned off when $GLOBALS['wp_app_framework_test_options']['show_avatars'] is false.
 *
 * @param array<string, mixed>|null $args
 */
function get_avatar(mixed $id_or_email, int $size = 96, string $default_value = '', string $alt = '', ?array $args = null): string|false
{
    return get_option('show_avatars', true) === false ? false : sprintf('<img class="avatar" data-user="%s" width="%d">', (string) $id_or_email, $size);
}

function home_url(string $path = '', ?string $scheme = null): string
{
    return 'https://example.test/' . ltrim($path, '/');
}

/**
 * The options blogname and blogdescription, as stored (escaped).
 */
function get_bloginfo(string $show = '', string $filter = 'raw'): string
{
    $option = ['name' => 'blogname', 'description' => 'blogdescription'][$show] ?? null;

    return $option === null ? '' : (string) get_option($option, '');
}

function wp_login_url(string $redirect = '', bool $force_reauth = false): string
{
    return 'https://example.test/wp-login.php' . ($redirect === '' ? '' : '?redirect_to=' . urlencode($redirect));
}

/**
 * Like WordPress: escaped by wp_nonce_url().
 */
function wp_logout_url(string $redirect = ''): string
{
    return esc_html('https://example.test/wp-login.php?action=logout' . ($redirect === '' ? '' : '&redirect_to=' . urlencode($redirect)) . '&_wpnonce=nonce');
}

/**
 * $GLOBALS['wp_app_framework_test_logged_in'].
 */
function is_user_logged_in(): bool
{
    return $GLOBALS['wp_app_framework_test_logged_in'] ?? false;
}

/**
 * Simplified: in UTC; false for the format "fail".
 */
function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string|false
{
    return $format === 'fail' ? false : gmdate($format, $timestamp ?? time());
}

/**
 * Simplified: in minutes.
 */
function human_time_diff(int $from, int $to = 0): string
{
    return sprintf('%d minutes', intdiv(abs(($to === 0 ? time() : $to) - $from), 60));
}

function number_format_i18n(float $number, int $decimals = 0): string
{
    return number_format($number, $decimals, ',', '.');
}

/**
 * Simplified: bytes and KB; false for negative sizes.
 */
function size_format(int|string $bytes, int $decimals = 0): string|false
{
    $bytes = (int) $bytes;

    return $bytes < 0 ? false : ($bytes < 1024 ? $bytes . ' B' : number_format($bytes / 1024, $decimals) . ' KB');
}

/**
 * Records deactivation hooks in $GLOBALS['wp_app_framework_test_deactivation'] by plugin file.
 */
function register_deactivation_hook(string $file, callable $callback): void
{
    $GLOBALS['wp_app_framework_test_deactivation'][$file] = $callback;
}

/**
 * Scheduled events in $GLOBALS['wp_app_framework_test_cron'] like the option "cron" of WordPress:
 * timestamp => hook => md5(serialize($args)) => ['schedule' => name|false, 'args' => $args, 'interval' => seconds].
 * Each write is counted in $GLOBALS['wp_app_framework_test_cron_writes']; with
 * $GLOBALS['wp_app_framework_test_cron_fail'] set, writes fail like _set_cron_array() (WP_Error "could_not_set").
 *
 * @return array<int, array<string, array<string, array{schedule: string|false, args: array<mixed>, interval?: int}>>>
 */
function wp_app_framework_test_cron(): array
{
    $cron = $GLOBALS['wp_app_framework_test_cron'] ?? [];
    ksort($cron);

    return $cron;
}

/**
 * @param array<int, array<string, array<string, array{schedule: string|false, args: array<mixed>, interval?: int}>>> $cron
 */
function wp_app_framework_test_cron_save(array $cron, bool $wp_error): bool|WP_Error
{
    if ($GLOBALS['wp_app_framework_test_cron_fail'] ?? false) {
        return $wp_error ? new WP_Error('could_not_set', 'The cron event list could not be saved.') : false;
    }
    $GLOBALS['wp_app_framework_test_cron'] = array_filter(array_map(array_filter(...), $cron));
    $GLOBALS['wp_app_framework_test_cron_writes'] = ($GLOBALS['wp_app_framework_test_cron_writes'] ?? 0) + 1;

    return true;
}

/**
 * @param array<mixed> $args
 */
function wp_next_scheduled(string $hook, array $args = []): int|false
{
    $event = wp_get_scheduled_event($hook, $args);

    return $event === false ? false : $event->timestamp;
}

/**
 * Like WordPress: the earliest event of the hook with exactly these arguments.
 *
 * @param array<mixed> $args
 */
function wp_get_scheduled_event(string $hook, array $args = [], ?int $timestamp = null): object|false
{
    $key = md5(serialize($args));
    foreach (wp_app_framework_test_cron() as $time => $hooks) {
        if (isset($hooks[$hook][$key]) && ($timestamp === null || $timestamp === $time)) {
            $event = (object) ['hook' => $hook, 'timestamp' => $time, 'schedule' => $hooks[$hook][$key]['schedule'], 'args' => $args];
            if (isset($hooks[$hook][$key]['interval'])) {
                $event->interval = $hooks[$hook][$key]['interval'];
            }

            return $event;
        }
    }

    return false;
}

/**
 * Like WordPress: the four core intervals and those of the filter cron_schedules (core wins on equal names).
 *
 * @return array<string, array{interval: int, display: string}>
 */
function wp_get_schedules(): array
{
    /** @var array<string, array{interval: int, display: string}> $custom */
    $custom = apply_filters('cron_schedules', []);

    return array_merge($custom, [
        'hourly' => ['interval' => 3600, 'display' => 'Once Hourly'],
        'twicedaily' => ['interval' => 43200, 'display' => 'Twice Daily'],
        'daily' => ['interval' => 86400, 'display' => 'Once Daily'],
        'weekly' => ['interval' => 604800, 'display' => 'Once Weekly'],
    ]);
}

/**
 * Like WordPress: checks the timestamp and the recurrence, no check for duplicates.
 *
 * @param array<mixed> $args
 */
function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = [], bool $wp_error = false): bool|WP_Error
{
    if ($timestamp <= 0) {
        return $wp_error ? new WP_Error('invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.') : false;
    }
    $schedules = wp_get_schedules();
    if (!isset($schedules[$recurrence])) {
        return $wp_error ? new WP_Error('invalid_schedule', 'Event schedule does not exist.') : false;
    }
    $cron = wp_app_framework_test_cron();
    $cron[$timestamp][$hook][md5(serialize($args))] = ['schedule' => $recurrence, 'args' => $args, 'interval' => $schedules[$recurrence]['interval']];

    return wp_app_framework_test_cron_save($cron, $wp_error);
}

/**
 * Like WordPress: checks the timestamp; an identical event (hook and arguments) within 10 minutes is a duplicate, and
 * for a time in the past or the next 10 minutes so is every earlier one.
 *
 * @param array<mixed> $args
 */
function wp_schedule_single_event(int $timestamp, string $hook, array $args = [], bool $wp_error = false): bool|WP_Error
{
    if ($timestamp <= 0) {
        return $wp_error ? new WP_Error('invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.') : false;
    }
    $key = md5(serialize($args));
    $min = $timestamp < time() + 600 ? 0 : $timestamp - 600;
    $max = $timestamp < time() ? time() + 600 : $timestamp + 600;
    $cron = wp_app_framework_test_cron();
    foreach ($cron as $time => $hooks) {
        if ($time >= $min && $time <= $max && isset($hooks[$hook][$key])) {
            return $wp_error ? new WP_Error('duplicate_event', 'A duplicate event already exists.') : false;
        }
    }
    $cron[$timestamp][$hook][$key] = ['schedule' => false, 'args' => $args];

    return wp_app_framework_test_cron_save($cron, $wp_error);
}

/**
 * Like WordPress: removes every event of the hook with exactly these arguments and returns their number.
 *
 * @param array<mixed> $args
 */
function wp_clear_scheduled_hook(string $hook, array $args = [], bool $wp_error = false): int|false|WP_Error
{
    $key = md5(serialize($args));
    $cron = wp_app_framework_test_cron();
    $count = 0;
    foreach ($cron as $time => $hooks) {
        if (isset($hooks[$hook][$key])) {
            unset($cron[$time][$hook][$key]);
            $count++;
        }
    }
    if ($count === 0) {
        return 0;
    }
    $saved = wp_app_framework_test_cron_save($cron, $wp_error);

    return $saved === true ? $count : $saved;
}

/**
 * Like WordPress: removes every event of the hook, whatever its arguments, and returns their number.
 */
function wp_unschedule_hook(string $hook, bool $wp_error = false): int|false|WP_Error
{
    $cron = wp_app_framework_test_cron();
    $count = 0;
    foreach ($cron as $time => $hooks) {
        $count += count($hooks[$hook] ?? []);
        unset($cron[$time][$hook]);
    }
    if ($count === 0) {
        return 0;
    }
    $saved = wp_app_framework_test_cron_save($cron, $wp_error);

    return $saved === true ? $count : $saved;
}

/**
 * The time zone of the site: $GLOBALS['wp_app_framework_test_timezone'], UTC by default.
 */
function wp_timezone(): DateTimeZone
{
    return new DateTimeZone($GLOBALS['wp_app_framework_test_timezone'] ?? 'UTC');
}

/**
 * $GLOBALS['wp_app_framework_test_blog_id'], 1 by default.
 */
function get_current_blog_id(): int
{
    return $GLOBALS['wp_app_framework_test_blog_id'] ?? 1;
}

/**
 * $GLOBALS['wp_app_framework_test_object_cache_enabled']; like WordPress (load.php), the global is returned unchanged,
 * null without a drop-in that set it.
 */
function wp_using_ext_object_cache(?bool $using = null): ?bool
{
    return $GLOBALS['wp_app_framework_test_object_cache_enabled'] ?? null;
}

/**
 * The object cache in $GLOBALS['wp_app_framework_test_object_cache'][$group][$key] (expiration is recorded, not applied).
 */
function wp_cache_get(int|string $key, string $group = '', bool $force = false, ?bool &$found = null): mixed
{
    $found = isset($GLOBALS['wp_app_framework_test_object_cache'][$group]) && array_key_exists($key, $GLOBALS['wp_app_framework_test_object_cache'][$group]);

    return $found ? $GLOBALS['wp_app_framework_test_object_cache'][$group][$key]['data'] : false;
}

function wp_cache_set(int|string $key, mixed $data, string $group = '', int $expire = 0): bool
{
    $GLOBALS['wp_app_framework_test_object_cache'][$group][$key] = ['data' => $data, 'expire' => $expire];

    return true;
}

/**
 * Transients in $GLOBALS['wp_app_framework_test_transients'] (expiration is recorded, not applied).
 */
/**
 * Transients from $GLOBALS['wp_app_framework_test_transients']; each read is recorded in
 * $GLOBALS['wp_app_framework_test_transient_reads'] (WordPress queries the database for it without a persistent object
 * cache).
 */
function get_transient(string $transient): mixed
{
    $GLOBALS['wp_app_framework_test_transient_reads'][] = $transient;

    return $GLOBALS['wp_app_framework_test_transients'][$transient]['value'] ?? false;
}

function set_transient(string $transient, mixed $value, int $expiration = 0): bool
{
    $GLOBALS['wp_app_framework_test_transients'][$transient] = ['value' => $value, 'expiration' => $expiration];

    return true;
}

/**
 * Like WordPress: false if the option exists; fires add_option_{$option}. Autoload is recorded in
 * $GLOBALS['wp_app_framework_test_autoload'].
 */
function add_option(string $option, mixed $value = '', string $deprecated = '', bool|string|null $autoload = null): bool
{
    if (array_key_exists($option, $GLOBALS['wp_app_framework_test_options'] ?? [])) {
        return false;
    }
    $GLOBALS['wp_app_framework_test_options'][$option] = $value;
    $GLOBALS['wp_app_framework_test_autoload'][$option] = $autoload;
    do_action('add_option_' . $option, $option, $value);

    return true;
}

/**
 * Like WordPress: adds a missing option; fires update_option_{$option} if the value changed.
 */
function update_option(string $option, mixed $value, bool|string|null $autoload = null): bool
{
    if (!array_key_exists($option, $GLOBALS['wp_app_framework_test_options'] ?? [])) {
        return add_option($option, $value, '', $autoload);
    }
    $old = $GLOBALS['wp_app_framework_test_options'][$option];
    if ($old === $value) {
        return false;
    }
    $GLOBALS['wp_app_framework_test_options'][$option] = $value;
    do_action('update_option_' . $option, $old, $value, $option);

    return true;
}

/**
 * $GLOBALS['wp_app_framework_test_user']: the current user; a user with ID 0 if nobody is logged in.
 */
function wp_get_current_user(): WP_User
{
    return $GLOBALS['wp_app_framework_test_user'] ?? new WP_User(0);
}

function get_current_user_id(): int
{
    return wp_get_current_user()->ID;
}

function wp_parse_url(string $url, int $component = -1): mixed
{
    return parse_url($url, $component);
}

/**
 * @template T of string|array<mixed>
 * @param T $value
 * @return T
 */
function wp_unslash(string|array $value): string|array
{
    return is_array($value) ? array_map(static fn(mixed $item): mixed => is_string($item) || is_array($item) ? wp_unslash($item) : $item, $value) : stripslashes($value);
}

/**
 * The post type of a post from $GLOBALS['wp_app_framework_test_posts'].
 */
function get_post_type(int|WP_Post|null $post = null): string|false
{
    $post = get_post($post);

    return $post === null ? false : $post->post_type;
}

/**
 * Like WP_Scripts and WP_Styles: the handles enqueued in $GLOBALS['wp_app_framework_test_scripts'] or
 * $GLOBALS['wp_app_framework_test_styles'], in the order they were registered.
 */
abstract class WP_Dependencies
{
    /** @var list<string> */
    public array $queue = [];

    /**
     * @param array<string, array<string, mixed>> $registered
     */
    public function __construct(array $registered)
    {
        foreach ($registered as $handle => $item) {
            if ((bool) ($item['enqueued'] ?? false)) {
                $this->queue[] = $handle;
            }
        }
    }
}

final class WP_Scripts extends WP_Dependencies {}

final class WP_Styles extends WP_Dependencies {}

function wp_scripts(): WP_Scripts
{
    return new WP_Scripts($GLOBALS['wp_app_framework_test_scripts'] ?? []);
}

function wp_styles(): WP_Styles
{
    return new WP_Styles($GLOBALS['wp_app_framework_test_styles'] ?? []);
}

function wp_style_is(string $handle, string $status = 'enqueued'): bool
{
    $style = $GLOBALS['wp_app_framework_test_styles'][$handle] ?? null;

    return match ($status) {
        'registered' => $style !== null,
        'enqueued' => (bool) ($style['enqueued'] ?? false),
        default => false,
    };
}

/**
 * Script modules in $GLOBALS['wp_app_framework_test_modules'] (id => enqueued).
 */
final class WP_Script_Modules
{
    /**
     * @return list<string>
     */
    public function get_queue(): array
    {
        return array_keys(array_filter($GLOBALS['wp_app_framework_test_modules'] ?? []));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_registered(string $id): ?array
    {
        return array_key_exists($id, $GLOBALS['wp_app_framework_test_modules'] ?? []) ? ['id' => $id] : null;
    }
}

function wp_script_modules(): WP_Script_Modules
{
    return new WP_Script_Modules();
}

/**
 * @param list<string>|array<array<string, mixed>> $deps
 * @param array<string, mixed>                     $args
 */
function wp_enqueue_script_module(string $id, string $src = '', array $deps = [], string|false|null $version = false, array $args = []): void
{
    $GLOBALS['wp_app_framework_test_modules'][$id] = true;
}

const MINUTE_IN_SECONDS = 60;

function register_activation_hook(string $file, callable $callback): void
{
    $GLOBALS['wp_app_framework_test_activation'][$file] = $callback;
}

/**
 * Registered post types in $GLOBALS['wp_app_framework_test_post_types'] (name => args).
 *
 * @param array<string, mixed> $args
 */
function register_post_type(string $post_type, array|string $args = []): WP_Post_Type|WP_Error
{
    $GLOBALS['wp_app_framework_test_post_types'][$post_type] = $args;

    return new WP_Post_Type($post_type);
}

final class WP_Post_Type
{
    public function __construct(public string $name) {}
}

/**
 * Registered taxonomies in $GLOBALS['wp_app_framework_test_taxonomies'] (name => [object types, args]).
 *
 * @param array<string, mixed> $args
 */
function register_taxonomy(string $taxonomy, array|string $object_type, array|string $args = []): WP_Taxonomy|WP_Error
{
    $GLOBALS['wp_app_framework_test_taxonomies'][$taxonomy] = [(array) $object_type, $args];

    return new WP_Taxonomy($taxonomy);
}

final class WP_Taxonomy
{
    public function __construct(public string $name) {}
}

/**
 * Registered meta in $GLOBALS['wp_app_framework_test_registered_meta'] (post type => key => args).
 *
 * @param array<string, mixed> $args
 */
function register_post_meta(string $post_type, string $meta_key, array $args): bool
{
    $GLOBALS['wp_app_framework_test_registered_meta'][$post_type][$meta_key] = $args;

    return true;
}

/**
 * The supports of post types registered with register_post_type(); "post" and "page" support revisions.
 */
function post_type_supports(string $post_type, string $feature): bool
{
    if (in_array($post_type, ['post', 'page'], true)) {
        return in_array($feature, ['title', 'editor', 'revisions', 'custom-fields'], true);
    }
    $supports = $GLOBALS['wp_app_framework_test_post_types'][$post_type]['supports'] ?? [];

    return is_array($supports) && in_array($feature, $supports, true);
}

/**
 * Counts the flushes in $GLOBALS['wp_app_framework_test_flushes'].
 */
function flush_rewrite_rules(bool $hard = true): void
{
    $GLOBALS['wp_app_framework_test_flushes'] = ($GLOBALS['wp_app_framework_test_flushes'] ?? 0) + 1;
}

/**
 * Like WordPress: the options that are not stored with autoload off ($GLOBALS['wp_app_framework_test_autoload']).
 *
 * @return array<string, mixed>
 */
function wp_load_alloptions(bool $force_cache = false): array
{
    return array_filter(
        $GLOBALS['wp_app_framework_test_options'] ?? [],
        static fn(string $option): bool => !in_array($GLOBALS['wp_app_framework_test_autoload'][$option] ?? null, [false, 'off', 'no'], true),
        ARRAY_FILTER_USE_KEY,
    );
}

function delete_option(string $option): bool
{
    if (!array_key_exists($option, $GLOBALS['wp_app_framework_test_options'] ?? [])) {
        return false;
    }
    unset($GLOBALS['wp_app_framework_test_options'][$option], $GLOBALS['wp_app_framework_test_autoload'][$option]);
    do_action('delete_option_' . $option, $option);

    return true;
}

/**
 * Meta boxes in $GLOBALS['wp_app_framework_test_meta_boxes'] (id => box).
 */
function add_meta_box(string $id, string $title, callable $callback, string|array|WP_Screen|null $screen = null, string $context = 'advanced', string $priority = 'default', ?array $callback_args = null): void
{
    $GLOBALS['wp_app_framework_test_meta_boxes'][$id] = ['title' => $title, 'callback' => $callback, 'screen' => $screen, 'context' => $context, 'args' => $callback_args];
}

/**
 * Removed meta boxes in $GLOBALS['wp_app_framework_test_removed_meta_boxes'] as "id screen context".
 */
function remove_meta_box(string $id, string|array|WP_Screen $screen, string $context): void
{
    $GLOBALS['wp_app_framework_test_removed_meta_boxes'][] = sprintf('%s %s %s', $id, is_string($screen) ? $screen : '', $context);
}

/**
 * Adds a post to $GLOBALS['wp_app_framework_test_posts'] with the next ID (from 1000); the data is unslashed like
 * WordPress does. An empty title and content is an error, like WordPress with $wp_error.
 *
 * @param array<string, mixed> $postarr
 */
function wp_insert_post(array $postarr, bool $wp_error = false, bool $fire_after_hooks = true): int|WP_Error
{
    $data = wp_unslash($postarr);
    if (($data['post_title'] ?? '') === '' && ($data['post_content'] ?? '') === '' && ($data['post_excerpt'] ?? '') === '') {
        return $wp_error ? new WP_Error('empty_content', 'Content, title, and excerpt are empty.') : 0;
    }
    $id = max(999, ...array_keys($GLOBALS['wp_app_framework_test_posts'] ?? [0 => null])) + 1;
    $GLOBALS['wp_app_framework_test_posts'][$id] = new WP_Post((object) [
        ...$data,
        'ID' => $id,
        'post_author' => (string) ($data['post_author'] ?? '0'),
        'post_date_gmt' => '2026-10-13 12:00:00',
        'post_modified_gmt' => '2026-10-13 12:00:00',
    ]);

    return $id;
}

/**
 * Updates a post of $GLOBALS['wp_app_framework_test_posts']; the data is unslashed like WordPress does.
 *
 * @param array<string, mixed> $postarr
 */
function wp_update_post(array|object $postarr = [], bool $wp_error = false, bool $fire_after_hooks = true): int|WP_Error
{
    $data = wp_unslash((array) $postarr);
    $id = (int) ($data['ID'] ?? 0);
    $post = $GLOBALS['wp_app_framework_test_posts'][$id] ?? null;
    if (!$post instanceof WP_Post) {
        return $wp_error ? new WP_Error('invalid_post', 'Invalid post ID.') : 0;
    }
    $GLOBALS['wp_app_framework_test_posts'][$id] = new WP_Post((object) [...get_object_vars($post), ...$data, 'post_author' => (string) ($data['post_author'] ?? $post->post_author)]);

    return $id;
}

/**
 * Like WordPress with the trash enabled: without $force_delete, only posts and pages not yet in the trash go there.
 */
function wp_delete_post(int $post_id = 0, bool $force_delete = false): WP_Post|false|null
{
    $post = $GLOBALS['wp_app_framework_test_posts'][$post_id] ?? null;
    if (!$post instanceof WP_Post) {
        return null;
    }
    if (!$force_delete && in_array($post->post_type, ['post', 'page'], true) && $post->post_status !== 'trash') {
        return wp_trash_post($post_id);
    }
    unset($GLOBALS['wp_app_framework_test_posts'][$post_id]);

    return $post;
}

/**
 * Like WordPress with the trash enabled: false for a post already in the trash.
 */
function wp_trash_post(int $post_id = 0): WP_Post|false|null
{
    $post = $GLOBALS['wp_app_framework_test_posts'][$post_id] ?? null;
    if (!$post instanceof WP_Post) {
        return null;
    }
    if ($post->post_status === 'trash') {
        return false;
    }
    $post->post_status = 'trash';

    return $post;
}

function get_post_status(int|WP_Post|null $post = null): string|false
{
    $post = get_post($post);

    return $post instanceof WP_Post ? $post->post_status : false;
}

/**
 * Stores meta in $GLOBALS['wp_app_framework_test_meta'] unslashed and sanitized by the registered sanitize callback,
 * like update_metadata().
 */
function update_post_meta(int $post_id, string $meta_key, mixed $meta_value, mixed $prev_value = ''): int|bool
{
    $value = is_string($meta_value) || is_array($meta_value) ? wp_unslash($meta_value) : $meta_value;
    $type = get_post_type($post_id);
    $sanitize = $GLOBALS['wp_app_framework_test_registered_meta'][$type][$meta_key]['sanitize_callback'] ?? null;
    $GLOBALS['wp_app_framework_test_meta'][$post_id][$meta_key] = is_callable($sanitize) ? $sanitize($value) : $value;

    return true;
}

function delete_post_meta(int $post_id, string $meta_key, mixed $meta_value = ''): bool
{
    $exists = isset($GLOBALS['wp_app_framework_test_meta'][$post_id]) && array_key_exists($meta_key, $GLOBALS['wp_app_framework_test_meta'][$post_id]);
    unset($GLOBALS['wp_app_framework_test_meta'][$post_id][$meta_key]);

    return $exists;
}

function metadata_exists(string $meta_type, int $object_id, string $meta_key): bool
{
    return $meta_type === 'post'
        && isset($GLOBALS['wp_app_framework_test_meta'][$object_id])
        && array_key_exists($meta_key, $GLOBALS['wp_app_framework_test_meta'][$object_id]);
}

/**
 * @template T
 * @param T $value
 * @return T
 */
function wp_slash(mixed $value): mixed
{
    if (is_array($value)) {
        /** @var T */
        return array_map(wp_slash(...), $value);
    }

    /** @var T */
    return is_string($value) ? addslashes($value) : $value;
}

/**
 * Terms of objects in $GLOBALS['wp_app_framework_test_object_terms'] (object ID => taxonomy => term IDs).
 *
 * @param string|int|list<string|int> $terms
 * @return list<int>|WP_Error
 */
function wp_set_object_terms(int $object_id, string|int|array $terms, string $taxonomy, bool $append = false): array|WP_Error
{
    $ids = array_map('intval', (array) $terms);
    $GLOBALS['wp_app_framework_test_object_terms'][$object_id][$taxonomy] = $ids;

    return $ids;
}

/**
 * The terms of $GLOBALS['wp_app_framework_test_object_terms'] as WP_Term from $GLOBALS['wp_app_framework_test_terms'].
 *
 * @param int|list<int>          $object_ids
 * @param string|list<string>    $taxonomies
 * @param array<string, mixed>   $args
 * @return list<WP_Term>|WP_Error
 */
function wp_get_object_terms(int|array $object_ids, string|array $taxonomies, array $args = []): array|WP_Error
{
    $terms = [];
    foreach ((array) $object_ids as $id) {
        foreach ((array) $taxonomies as $taxonomy) {
            foreach ($GLOBALS['wp_app_framework_test_object_terms'][$id][$taxonomy] ?? [] as $termId) {
                $term = $GLOBALS['wp_app_framework_test_terms'][$termId] ?? null;
                if ($term instanceof WP_Term) {
                    $terms[] = $term;
                }
            }
        }
    }

    return $terms;
}

/**
 * Simplified: only "publish" is viewable.
 */
function is_post_status_viewable(string|object $post_status): bool
{
    return $post_status === 'publish';
}

/**
 * A nonce is "nonce-{action}" here.
 */
function wp_nonce_field(int|string $action = -1, string $name = '_wpnonce', bool $referer = true, bool $display = true): string
{
    $field = sprintf('<input type="hidden" name="%s" value="nonce-%s">', $name, $action);
    if ($display) {
        echo $field;
    }

    return $field;
}

function wp_verify_nonce(string $nonce, int|string $action = -1): int|false
{
    return $nonce === 'nonce-' . $action ? 1 : false;
}

function wp_is_post_revision(int|WP_Post $post): int|false
{
    return false;
}

function delete_transient(string $transient): bool
{
    $exists = isset($GLOBALS['wp_app_framework_test_transients'][$transient]);
    unset($GLOBALS['wp_app_framework_test_transients'][$transient]);

    return $exists;
}

/**
 * The current screen: $GLOBALS['wp_app_framework_test_screen'].
 */
function get_current_screen(): ?WP_Screen
{
    return $GLOBALS['wp_app_framework_test_screen'] ?? null;
}

final class WP_Screen
{
    public string $base = '';
    public string $post_type = '';
    private bool $is_block_editor = false;

    public function is_block_editor(?bool $set = null): bool
    {
        if ($set !== null) {
            $this->is_block_editor = $set;
        }

        return $this->is_block_editor;
    }
}

function remove_filter(string $hook_name, callable|string|array $callback, int $priority = 10): bool
{
    foreach ($GLOBALS['wp_app_framework_test_filters'][$hook_name] ?? [] as $index => $added) {
        if ($added === $callback) {
            unset($GLOBALS['wp_app_framework_test_filters'][$hook_name][$index], $GLOBALS['wp_app_framework_test_filter_args'][$hook_name][$index]);

            return true;
        }
    }

    return false;
}

/**
 * Records the IDs in $GLOBALS['wp_app_framework_test_primed'].
 *
 * @param int[] $ids
 */
function _prime_post_caches(array $ids, bool $update_term_cache = true, bool $update_meta_cache = true): void
{
    $GLOBALS['wp_app_framework_test_primed'][] = $ids;
}

/**
 * Like WordPress: query() records its arguments in $GLOBALS['wp_app_framework_test_queries'], passes the clauses
 * through the filter posts_clauses (recorded in $GLOBALS['wp_app_framework_test_query_clauses']) unless filters are
 * suppressed and returns the IDs and number of $GLOBALS['wp_app_framework_test_query_result'].
 */
final class WP_Query
{
    /** @var array<string, mixed> */
    public array $query_vars = [];

    /** @var array<int|WP_Post> */
    public array $posts = [];

    public int $found_posts = 0;

    public string $request = '';

    /**
     * @param array<string, mixed>|string $query
     * @return array<int|WP_Post>
     */
    public function query(array|string $query): array
    {
        $this->query_vars = is_array($query) ? $query : [];
        $GLOBALS['wp_app_framework_test_queries'][] = $this->query_vars;
        if (($this->query_vars['suppress_filters'] ?? false) !== true) {
            $GLOBALS['wp_app_framework_test_query_clauses'][] = apply_filters(
                'posts_clauses',
                ['where' => ' AND 1=1', 'groupby' => '', 'join' => '', 'orderby' => '', 'distinct' => '', 'fields' => 'wp_posts.ID', 'limits' => ''],
                $this,
            );
        }
        /** @var array{posts?: list<int>, found_posts?: int} $result */
        $result = $GLOBALS['wp_app_framework_test_query_result'] ?? [];
        $this->posts = $result['posts'] ?? [];
        $this->found_posts = $result['found_posts'] ?? count($this->posts);

        return $this->posts;
    }

    public function get(string $query_var, mixed $default_value = ''): mixed
    {
        return $this->query_vars[$query_var] ?? $default_value;
    }
}

/**
 * Like WordPress: table names and prepare() for %s (quoted) and %d.
 */
const OBJECT = 'OBJECT';

const ARRAY_A = 'ARRAY_A';

/**
 * Statements run against $GLOBALS['wp_app_framework_test_database'] (Tests\Support\Database), if set; without it,
 * queries return nothing. Placeholders like wpdb::prepare(): %i, %d, %f, %s.
 */
final class wpdb
{
    public string $prefix = 'wp_';

    public string $posts = 'wp_posts';

    public string $postmeta = 'wp_postmeta';

    public string $term_taxonomy = 'wp_term_taxonomy';

    public string $options = 'wp_options';

    public int $insert_id = 0;

    public int $rows_affected = 0;

    public string $last_error = '';

    public bool $suppress_errors = false;

    public function prepare(string $query, mixed ...$args): string
    {
        $index = 0;

        return (string) preg_replace_callback('/%[sdfi]/', static function (array $match) use ($args, &$index): string {
            $value = $args[$index++] ?? '';
            $string = is_scalar($value) ? (string) $value : '';

            return match ($match[0]) {
                '%d' => (string) (int) $value,
                '%f' => sprintf('%F', (float) $value),
                '%i' => '`' . str_replace('`', '``', $string) . '`',
                default => "'" . addslashes($string) . "'",
            };
        }, $query);
    }

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }

    public function remove_placeholder_escape(string $query): string
    {
        return $query;
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
    }

    public function suppress_errors(bool $suppress = true): bool
    {
        $previous = $this->suppress_errors;
        $this->suppress_errors = $suppress;

        return $previous;
    }

    public function query(string $query): int|bool
    {
        $result = $this->run($query);
        if ($result === false) {
            return false;
        }

        return is_int($result) ? $result : true;
    }

    /**
     * @return list<array<string, string|null>>|null
     */
    public function get_results(?string $query = null, string $output = OBJECT): ?array
    {
        $result = $this->run((string) $query);

        return is_array($result) ? $result : ($result === false ? null : []);
    }

    /**
     * @return array<string, string|null>|null
     */
    public function get_row(?string $query = null, string $output = OBJECT, int $y = 0): ?array
    {
        return $this->get_results($query, $output)[$y] ?? null;
    }

    public function get_var(?string $query = null, int $x = 0, int $y = 0): ?string
    {
        $result = $this->run((string) $query);
        if (is_array($result)) {
            $row = array_values($result[$y] ?? []);

            return $row[$x] ?? null;
        }

        return is_scalar($result) && !is_bool($result) ? (string) $result : null;
    }

    /**
     * @return list<string|null>
     */
    public function get_col(?string $query = null, int $x = 0): array
    {
        $result = $this->run((string) $query);

        return is_array($result) ? array_map(static fn(array $row): ?string => array_values($row)[$x] ?? null, $result) : [];
    }

    /**
     * @param array<string, mixed>      $data
     * @param list<string>|string|null  $format
     */
    public function insert(string $table, array $data, array|string|null $format = null): int|false
    {
        $database = self::database();
        $this->last_error = '';
        if ($database === null) {
            return false;
        }
        $result = $database->run(sprintf('INSERT INTO `%s` (%s)', $table, implode(', ', array_keys($data))));
        if ($result['error'] !== '') {
            $this->last_error = $result['error'];

            return false;
        }
        try {
            $this->insert_id = $database->insert($table, self::strings($data));
        } catch (RuntimeException $exception) {
            $this->last_error = $exception->getMessage();

            return false;
        }

        return 1;
    }

    /**
     * @param array<string, mixed>      $data
     * @param array<string, mixed>      $where
     * @param list<string>|string|null  $format
     * @param list<string>|string|null  $where_format
     */
    public function update(string $table, array $data, array $where, array|string|null $format = null, array|string|null $where_format = null): int|false
    {
        $database = self::database();
        $this->last_error = '';
        if ($database === null) {
            return false;
        }
        $result = $database->run(sprintf('UPDATE `%s` SET %s', $table, implode(', ', array_keys($data))));
        if ($result['error'] !== '') {
            $this->last_error = $result['error'];

            return false;
        }

        return $database->update($table, self::strings($data), array_map(strval(...), self::strings($where)));
    }

    /**
     * @param array<string, mixed>      $where
     * @param list<string>|string|null  $where_format
     */
    public function delete(string $table, array $where, array|string|null $where_format = null): int|false
    {
        $database = self::database();
        $this->last_error = '';
        if ($database === null) {
            return false;
        }
        $result = $database->run(sprintf('DELETE FROM `%s` BY %s', $table, implode(', ', array_keys($where))));
        if ($result['error'] !== '') {
            $this->last_error = $result['error'];

            return false;
        }

        return $database->delete($table, array_map(strval(...), self::strings($where)));
    }

    /**
     * @return list<array<string, string|null>>|int|bool|string|null
     */
    private function run(string $query): array|int|bool|string|null
    {
        $this->last_error = '';
        $this->rows_affected = 0;
        $database = self::database();
        if ($database === null) {
            return null;
        }
        $result = $database->run($query);
        $this->last_error = $result['error'];
        $this->rows_affected = $result['affected'];

        return $result['result'];
    }

    private static function database(): ?Saij\WpAppFramework\Tests\Support\Database
    {
        $database = $GLOBALS['wp_app_framework_test_database'] ?? null;

        return $database instanceof Saij\WpAppFramework\Tests\Support\Database ? $database : null;
    }

    /**
     * Values as wpdb sends them: strings, NULL for null.
     *
     * @param array<string, mixed> $data
     * @return array<string, string|null>
     */
    private static function strings(array $data): array
    {
        return array_map(static fn(mixed $value): ?string => $value === null ? null : (is_scalar($value) ? (string) $value : ''), $data);
    }
}

/**
 * $GLOBALS['wp_app_framework_test_installing'].
 */
function wp_installing(?bool $is_installing = null): bool
{
    return $GLOBALS['wp_app_framework_test_installing'] ?? false;
}

$GLOBALS['wpdb'] = new wpdb();

/**
 * Records uninstall hooks in $GLOBALS['wp_app_framework_test_uninstall'] by plugin file.
 */
function register_uninstall_hook(string $file, callable $callback): void
{
    $GLOBALS['wp_app_framework_test_uninstall'][$file] = $callback;
}

/**
 * $GLOBALS['wp_app_framework_test_multisite'], false by default.
 */
function is_multisite(): bool
{
    return $GLOBALS['wp_app_framework_test_multisite'] ?? false;
}

function get_current_network_id(): int
{
    return 1;
}

/**
 * The IDs of $GLOBALS['wp_app_framework_test_sites'] (site ID => network ID), of one network with "network_id"; only
 * "fields" => "ids" is supported. The arguments are recorded in $GLOBALS['wp_app_framework_test_site_queries'].
 *
 * @param string|array<string, mixed> $args
 * @return list<int|WP_Site>|int
 */
function get_sites(string|array $args = []): array|int
{
    $args = is_array($args) ? $args : [];
    $GLOBALS['wp_app_framework_test_site_queries'][] = $args;
    $sites = [];
    foreach ($GLOBALS['wp_app_framework_test_sites'] ?? [1 => 1] as $site => $network) {
        if (!isset($args['network_id']) || $args['network_id'] === $network) {
            $sites[] = $site;
        }
    }

    return $sites;
}

/**
 * Sets the current blog ID (get_current_blog_id()) and records the switch in
 * $GLOBALS['wp_app_framework_test_switches'].
 */
function switch_to_blog(int $new_blog_id, ?bool $deprecated = null): true
{
    $GLOBALS['wp_app_framework_test_blog_stack'][] = get_current_blog_id();
    $GLOBALS['wp_app_framework_test_blog_id'] = $new_blog_id;
    $GLOBALS['wp_app_framework_test_switches'][] = $new_blog_id;

    return true;
}

function restore_current_blog(): bool
{
    $stack = $GLOBALS['wp_app_framework_test_blog_stack'] ?? [];
    if ($stack === []) {
        return false;
    }
    $GLOBALS['wp_app_framework_test_blog_id'] = array_pop($stack);
    $GLOBALS['wp_app_framework_test_blog_stack'] = $stack;

    return true;
}

/**
 * Network options from $GLOBALS['wp_app_framework_test_site_options'].
 */
function get_site_option(string $option, mixed $default_value = false, bool $deprecated = true): mixed
{
    return $GLOBALS['wp_app_framework_test_site_options'][$option] ?? $default_value;
}

/**
 * Removes the key from all posts in $GLOBALS['wp_app_framework_test_meta'] and records it in
 * $GLOBALS['wp_app_framework_test_deleted_meta_keys'].
 */
function delete_post_meta_by_key(string $post_meta_key): bool
{
    $GLOBALS['wp_app_framework_test_deleted_meta_keys'][] = $post_meta_key;
    $found = false;
    foreach ($GLOBALS['wp_app_framework_test_meta'] ?? [] as $postId => $meta) {
        if (array_key_exists($post_meta_key, $meta)) {
            unset($GLOBALS['wp_app_framework_test_meta'][$postId][$post_meta_key]);
            $found = true;
        }
    }

    return $found;
}

/**
 * Records deleted terms in $GLOBALS['wp_app_framework_test_deleted_terms'] as "taxonomy ID"; terms listed in
 * $GLOBALS['wp_app_framework_test_term_failures'] fail with a WP_Error.
 *
 * @param array<string, mixed>|string $args
 */
function wp_delete_term(int $term, string $taxonomy, array|string $args = []): bool|int|WP_Error
{
    if (in_array($term, $GLOBALS['wp_app_framework_test_term_failures'] ?? [], true)) {
        return new WP_Error('invalid_term', 'Term does not exist.');
    }
    $GLOBALS['wp_app_framework_test_deleted_terms'][] = $taxonomy . ' ' . $term;

    return true;
}

/**
 * Only the ID of the site is known.
 */
final class WP_Site
{
    public string $blog_id = '0';

    public function __construct(object $site)
    {
        $id = get_object_vars($site)['blog_id'] ?? '0';
        $this->blog_id = is_scalar($id) ? (string) $id : '0';
    }
}

/**
 * Test double of WP_HTML_Tag_Processor (WordPress 7.1, wp-includes/html-api/class-wp-html-tag-processor.php) for the
 * first tag of a document only: finds it, reads its name, and adds classes and sets or removes attributes on it.
 * Like WordPress, it changes existing attributes in place and inserts new ones after the tag name, sorted by name.
 */
class WP_HTML_Tag_Processor
{
    /** @var array<string, string|true> Attributes of the found tag, lowercase names. */
    private array $attributes = [];

    /** @var list<string> Names of the attributes the tag had, in their order. */
    private array $original = [];

    private ?string $tag = null;

    private int $start = 0;

    private int $length = 0;

    private string $html;

    /**
     * @param string $html
     */
    public function __construct($html)
    {
        $this->html = $html;
    }

    /**
     * @param array<string, mixed>|string|null $query
     */
    public function next_tag($query = null): bool
    {
        if ($this->tag !== null || preg_match('#<([a-zA-Z][a-zA-Z0-9-]*)((?:\s+[^\s=/>]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?)*)\s*/?>#', $this->html, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return false;
        }
        $this->tag = strtoupper($match[1][0]);
        $this->start = $match[0][1];
        $this->length = strlen($match[0][0]);
        preg_match_all('#([^\s=/>]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?#', $match[2][0], $attributes, PREG_SET_ORDER);
        foreach ($attributes as $attribute) {
            $value = ($attribute[2] ?? '') . ($attribute[3] ?? '') . ($attribute[4] ?? '');
            $this->attributes[strtolower($attribute[1])] = isset($attribute[2]) || isset($attribute[3]) || isset($attribute[4]) ? html_entity_decode($value) : true;
        }
        $this->original = array_keys($this->attributes);

        return true;
    }

    public function get_tag(): ?string
    {
        return $this->tag;
    }

    /**
     * @param string      $name
     * @param string|bool $value
     */
    public function set_attribute($name, $value): bool
    {
        if ($value === false) {
            return $this->remove_attribute($name);
        }
        $this->attributes[strtolower($name)] = $value === true ? true : $value;

        return true;
    }

    /**
     * @param string $name
     */
    public function remove_attribute($name): bool
    {
        unset($this->attributes[strtolower($name)]);

        return true;
    }

    /**
     * @param string $class_name
     */
    public function add_class($class_name): bool
    {
        $classes = isset($this->attributes['class']) && is_string($this->attributes['class']) ? preg_split('/\s+/', trim($this->attributes['class']), -1, PREG_SPLIT_NO_EMPTY) : [];
        if (!in_array($class_name, (array) $classes, true)) {
            $classes[] = $class_name;
        }
        $this->attributes['class'] = implode(' ', (array) $classes);

        return true;
    }

    public function get_updated_html(): string
    {
        if ($this->tag === null) {
            return $this->html;
        }
        $added = array_values(array_diff(array_keys($this->attributes), $this->original));
        sort($added, SORT_STRING);
        $tag = '<' . strtolower($this->tag);
        foreach ([...$added, ...array_values(array_intersect($this->original, array_keys($this->attributes)))] as $name) {
            $value = $this->attributes[$name];
            $tag .= ' ' . $name . ($value === true ? '' : '="' . htmlspecialchars($value, ENT_QUOTES) . '"');
        }

        return substr($this->html, 0, $this->start) . $tag . '>' . substr($this->html, $this->start + $this->length);
    }
}

function content_url(string $path = ''): string
{
    return 'https://example.test/wp-content' . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

/**
 * Simplified: the IDs of the attachments in $GLOBALS['wp_app_framework_test_attachments'] with the meta value of
 * `meta_key` and `meta_value` (fields "ids" only).
 *
 * @param array<string, mixed>|string|null $args
 * @return list<int>
 */
function get_posts($args = null): array
{
    $args = is_array($args) ? $args : [];
    $ids = [];
    foreach (array_keys($GLOBALS['wp_app_framework_test_attachments'] ?? []) as $id) {
        if (($GLOBALS['wp_app_framework_test_meta'][$id][$args['meta_key'] ?? ''] ?? null) === ($args['meta_value'] ?? null)) {
            $ids[] = (int) $id;
        }
    }

    return array_slice($ids, 0, is_int($args['numberposts'] ?? null) ? $args['numberposts'] : 5);
}

/**
 * Writes the file to $GLOBALS['wp_app_framework_test_uploads'] (a directory) and records the call.
 *
 * @param string      $name
 * @param null|string $deprecated
 * @param string      $bits
 * @param string|null $time
 * @return array{file: string, url: string, type: string, error: string|false}
 */
function wp_upload_bits($name, $deprecated, $bits, $time = null): array
{
    $directory = $GLOBALS['wp_app_framework_test_uploads'] ?? sys_get_temp_dir();
    $file = $directory . '/' . $name;
    file_put_contents($file, $bits);

    return ['file' => $file, 'url' => 'https://example.test/uploads/' . $name, 'type' => '', 'error' => false];
}

/**
 * Simplified: the type by extension for a few image and document types.
 *
 * @param string                     $filename
 * @param array<string, string>|null $mimes
 * @return array{ext: string|false, type: string|false}
 */
function wp_check_filetype($filename, $mimes = null): array
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'pdf' => 'application/pdf'];

    return isset($types[$extension]) ? ['ext' => $extension, 'type' => $types[$extension]] : ['ext' => false, 'type' => false];
}

/**
 * Records the attachment in $GLOBALS['wp_app_framework_test_attachments'] with the next ID.
 *
 * @param array<string, mixed>|string $args
 * @param string|false                $file
 * @param int                         $parent_post_id
 * @param bool                        $wp_error
 * @param bool                        $fire_after_hooks
 */
function wp_insert_attachment($args, $file = false, $parent_post_id = 0, $wp_error = false, $fire_after_hooks = true): int|WP_Error
{
    $id = 1000 + count($GLOBALS['wp_app_framework_test_attachments'] ?? []);
    $GLOBALS['wp_app_framework_test_attachments'][$id] = ['args' => $args, 'file' => $file];

    return $id;
}

/**
 * Simplified: the file name, as WordPress stores it for a file without intermediate sizes.
 *
 * @param int    $attachment_id
 * @param string $file
 * @return array<string, mixed>
 */
function wp_generate_attachment_metadata($attachment_id, $file): array
{
    return ['file' => basename($file)];
}

/**
 * Records the metadata of an attachment of wp_insert_attachment().
 *
 * @param int          $attachment_id
 * @param array<mixed> $data
 */
function wp_update_attachment_metadata($attachment_id, $data): int|false
{
    $GLOBALS['wp_app_framework_test_attachments'][$attachment_id]['metadata'] = $data;

    return $attachment_id;
}
