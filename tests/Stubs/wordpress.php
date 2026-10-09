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
