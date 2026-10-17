<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\View;

use InvalidArgumentException;
use LogicException;
use OutOfBoundsException;

/**
 * Data from PHP for the scripts of one framework instance: its views (of
 * blocks, block supports and the document), its editor scripts and its shared
 * modules read a value with `this.data(key)` or `getData(key)`.
 *
 * Inject it and set values before they are printed: on the front end the
 * framework prints them with the view runtime in the footer, so values from
 * hooks and from render() reach the views; in the editor it prints them with
 * the editor runtime at the end of `enqueue_block_editor_assets`. Setting a
 * value afterwards is an error. Every visitor can read the values.
 *
 * ```php
 * #[Hook('wp')]
 * public function provideMap(ViewData $viewData): void
 * {
 *     $viewData->set('map', ['zoom' => 12]);
 * }
 * ```
 *
 * @api Services
 */
final class ViewData
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var list<array<string, mixed>> Values set while cached blocks render, innermost last. */
    private array $recordings = [];

    /** Where the values were printed; null before. */
    private ?string $printed = null;

    /**
     * @param string $slug  Slug of the instance, for messages.
     * @param bool   $debug WP_DEBUG: a value set after printing throws instead of being logged.
     *
     * @internal Created by the framework; inject the service.
     */
    public function __construct(
        private readonly string $slug,
        private readonly bool $debug,
    ) {}

    /**
     * Sets a value; a later call with the same key replaces it.
     *
     * @param string $key   Letters, digits, "-" and "_", starting with a letter.
     * @param mixed  $value null, a scalar or an array of these (no objects, no NAN or INF).
     * @throws InvalidArgumentException If the key or the value is invalid.
     * @throws LogicException           If the values are already printed and WP_DEBUG is on.
     */
    public function set(string $key, mixed $value): void
    {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $key) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'The view data key %s of %s is invalid: use letters, digits, "-" and "_", starting with a letter.',
                json_encode($key),
                $this->slug,
            ));
        }
        $invalid = self::invalid($value, $key);
        if ($invalid !== null) {
            throw new InvalidArgumentException(sprintf(
                'The view data "%s" of %s must be null, a scalar or an array of these; %s.',
                $key,
                $this->slug,
                $invalid,
            ));
        }
        if ($this->printed !== null) {
            $message = sprintf(
                'The view data "%s" of %s was set after it was printed (%s); set view data earlier, e.g. on the action "wp" or in render().',
                $key,
                $this->slug,
                $this->printed,
            );
            if ($this->debug) {
                throw new LogicException($message);
            }
            error_log('wp-app-framework: ' . $message);

            return;
        }

        $this->values[$key] = $value;
        foreach (array_keys($this->recordings) as $index) {
            $this->recordings[$index][$key] = $value;
        }
    }

    /**
     * Whether a value is set for the key.
     *
     * @param string $key The key the value was set with.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * The value set for the key.
     *
     * @param string $key The key the value was set with.
     * @throws OutOfBoundsException If no value is set for the key.
     */
    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new OutOfBoundsException(sprintf('%s has no view data "%s".', $this->slug, $key));
        }

        return $this->values[$key];
    }

    /**
     * Starts recording the values set while a cached block renders.
     *
     * @internal
     */
    public function startRecording(): void
    {
        $this->recordings[] = [];
    }

    /**
     * Ends the innermost recording.
     *
     * @internal
     * @return array<string, mixed> The values set since startRecording().
     */
    public function stopRecording(): array
    {
        return array_pop($this->recordings) ?? throw new LogicException('No view data is being recorded.');
    }

    /**
     * Sets recorded values again, when a cached block is used instead of rendering.
     *
     * @internal
     * @param array<string, mixed> $values
     */
    public function replay(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * The script that prints the values as wpAppFramework[slug].data, and marks them printed.
     *
     * @internal
     * @param string $where Where they are printed, for the message of a later set().
     */
    public function print(string $where): string
    {
        $this->printed = $where;
        $slug = (string) wp_json_encode($this->slug);

        return sprintf(
            'window.wpAppFramework = window.wpAppFramework || {}; window.wpAppFramework[%1$s] = window.wpAppFramework[%1$s] || {}; window.wpAppFramework[%1$s].data = %2$s;',
            $slug,
            wp_json_encode((object) $this->values, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Why a value cannot be view data; null if it can.
     */
    private static function invalid(mixed $value, string $path): ?string
    {
        if (is_float($value) && !is_finite($value)) {
            return sprintf('%s is %s', $path, $value);
        }
        if ($value === null || is_scalar($value)) {
            return null;
        }
        if (!is_array($value)) {
            return sprintf('%s is %s', $path, get_debug_type($value));
        }
        foreach ($value as $index => $item) {
            $invalid = self::invalid($item, $path . '[' . $index . ']');
            if ($invalid !== null) {
                return $invalid;
            }
        }

        return null;
    }
}
