<?php

declare(strict_types=1);

/*
 * Minimal test doubles of the WordPress classes and functions used by the
 * framework's runtime code. They implement only what the framework calls;
 * WordPress is not loaded in unit tests.
 */

final class WP_Error
{
    public function __construct(
        private string $code = '',
        private string $message = '',
        private mixed $data = '',
    ) {
    }

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
    ) {
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
 * Like WordPress, query/body parameters take precedence over URL parameters in get_param().
 */
final class WP_REST_Request
{
    /**
     * @param array<string, mixed> $params    Query, body and default parameters.
     * @param array<string, mixed> $urlParams Parameters matched from the route.
     */
    public function __construct(
        private array $params = [],
        private array $urlParams = [],
    ) {
    }

    public function has_param(string $key): bool
    {
        return array_key_exists($key, $this->params) || array_key_exists($key, $this->urlParams);
    }

    public function get_param(string $key): mixed
    {
        return array_key_exists($key, $this->params) ? $this->params[$key] : ($this->urlParams[$key] ?? null);
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
 * Simplified: checks only the integer type (and null for nullable types).
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
 * Holds only the block name; enough to pass it to render callbacks.
 */
final class WP_Block
{
    public function __construct(private string $name = '')
    {
    }

    public function getName(): string
    {
        return $this->name;
    }
}

/**
 * Records the registered block types in $GLOBALS['wp_app_framework_test_blocks'].
 *
 * @param array<string, mixed> $args
 */
function register_block_type(string $name, array $args = []): bool
{
    $GLOBALS['wp_app_framework_test_blocks'][$name] = $args;

    return true;
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
    public function register(string $name, array $args): void
    {
        $this->types[$name] = new WP_Block_Type($name, $args);
    }

    public function unregister_all(): void
    {
        $this->types = [];
    }

    public function get_registered(string $name): ?WP_Block_Type
    {
        return $this->types[$name] ?? null;
    }
}

final class WP_Block_Type
{
    /**
     * @param array<string, mixed> $args
     */
    public function __construct(private string $name, private array $args)
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function get_attributes(): array
    {
        return ($this->args['attributes'] ?? []) + ['lock' => ['type' => 'object'], 'metadata' => ['type' => 'object']];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes): string
    {
        return ($this->args['render_callback'])($attributes, '', new WP_Block($this->name));
    }
}

/**
 * @param array{blockName: string, attrs: array<string, mixed>} $block
 */
function render_block(array $block): string
{
    return WP_Block_Type_Registry::get_instance()->get_registered($block['blockName'])?->render($block['attrs']) ?? '';
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
function get_post(int $post): ?object
{
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
