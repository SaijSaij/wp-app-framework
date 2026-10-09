<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use LogicException;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Value\ValueFactory;
use Throwable;
use WP_Block;

/**
 * Registers the blocks of the manifest with WordPress (on init).
 *
 * The block service is resolved only when a block is rendered. The attributes
 * pass through the value factory. Errors are rethrown when debugging is
 * enabled; otherwise they are logged and the block renders nothing.
 *
 * @internal
 *
 * @phpstan-import-type BlockEntry from Manifest
 */
#[NoService]
final readonly class BlockRegistrar
{
    public function __construct(
        private Container $container,
        private ValueFactory $values,
        private bool $debug,
    ) {
    }

    /**
     * @param list<BlockEntry> $blocks
     */
    public function register(array $blocks): void
    {
        foreach ($blocks as $block) {
            register_block_type($block['name'], [
                ...$block['args'],
                // WP_Block_Type::render() calls render callbacks without the block instance.
                'render_callback' => fn (array $attributes, string $content = '', ?WP_Block $instance = null): string
                    => $this->render($block, $attributes, $content, $instance),
            ]);
        }
    }

    /**
     * @param BlockEntry           $block
     * @param array<string, mixed> $attributes Attributes as WordPress prepared them: validated, defaults applied.
     */
    public function render(array $block, array $attributes, string $content, ?WP_Block $instance): string
    {
        try {
            $named = [];
            foreach ($block['arguments'] as $argument) {
                $named[$argument['param']] = match ($argument['kind']) {
                    'attributes' => $this->values->createAttributes($argument['class'], $attributes, $block['args']['attributes']),
                    'content' => $content,
                    'block' => $instance ?? throw new LogicException(sprintf(
                        'Block %s was rendered without a WP_Block instance, but %s::render() needs one.',
                        $block['name'],
                        $block['class'],
                    )),
                };
            }

            return $this->container->get($block['class'])->render(...$named);
        } catch (Throwable $exception) {
            if ($this->debug) {
                throw $exception;
            }

            error_log(sprintf(
                'wp-app-framework: block %s (%s::render()) failed: %s: %s in %s:%d',
                $block['name'],
                $block['class'],
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ));

            return '';
        }
    }
}
