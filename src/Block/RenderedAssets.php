<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Block;

use LogicException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * The scripts, styles and script modules a block enqueues while it renders,
 * recorded for its cache entry and enqueued again when the entry is used.
 *
 * @internal
 *
 * @phpstan-type Queues array{scripts: list<non-empty-string>, styles: list<non-empty-string>, modules: list<non-empty-string>}
 */
#[NoService]
final class RenderedAssets
{
    /**
     * @return Queues
     */
    public static function snapshot(): array
    {
        return [
            'scripts' => self::handles(wp_scripts()->queue),
            'styles' => self::handles(wp_styles()->queue),
            'modules' => self::handles(wp_script_modules()->get_queue()),
        ];
    }

    /**
     * What was enqueued between two snapshots, without the given script handles.
     *
     * @param Queues       $before
     * @param Queues       $after
     * @param list<string> $excludedScripts
     * @return Queues
     */
    public static function added(array $before, array $after, array $excludedScripts): array
    {
        return [
            'scripts' => array_values(array_diff($after['scripts'], $before['scripts'], $excludedScripts)),
            'styles' => array_values(array_diff($after['styles'], $before['styles'])),
            'modules' => array_values(array_diff($after['modules'], $before['modules'])),
        ];
    }

    /**
     * Enqueues recorded assets again; each must still be registered.
     *
     * @param Queues $assets
     */
    public static function enqueue(array $assets, string $block): void
    {
        foreach ($assets['scripts'] as $handle) {
            if (!wp_script_is($handle, 'registered')) {
                throw new LogicException(self::missing('script', $handle, $block));
            }
            wp_enqueue_script($handle);
        }
        foreach ($assets['styles'] as $handle) {
            if (!wp_style_is($handle, 'registered')) {
                throw new LogicException(self::missing('style', $handle, $block));
            }
            wp_enqueue_style($handle);
        }
        foreach ($assets['modules'] as $id) {
            if (wp_script_modules()->get_registered($id) === null) {
                throw new LogicException(self::missing('script module', $id, $block));
            }
            wp_enqueue_script_module($id);
        }
    }

    /**
     * @param array<mixed> $queue
     * @return list<non-empty-string>
     */
    private static function handles(array $queue): array
    {
        return array_values(array_filter($queue, static fn(mixed $handle): bool => is_string($handle) && $handle !== ''));
    }

    private static function missing(string $kind, string $handle, string $block): string
    {
        return sprintf(
            'Block %s is cached (#[Cache]) and enqueued the %s "%s" when it rendered, but the %s is not registered now that the block comes from the cache; register it before blocks render (e.g. on init) instead of in render().',
            $block,
            $kind,
            $handle,
            $kind,
        );
    }
}
