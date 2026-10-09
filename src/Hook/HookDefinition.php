<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Hook;

use Saij\WpAppFramework\DependencyInjection\NoService;


/**
 * A validated `#[Hook]` method.
 */
#[NoService]
final readonly class HookDefinition
{
    /**
     * @param class-string $class
     * @param list<array{param: string, service: class-string}|array{param: string, index: int}> $arguments
     *        Method parameters in declaration order: injected service or index of the WordPress argument.
     */
    public function __construct(
        public string $name,
        public int $priority,
        public bool $filter,
        public string $class,
        public string $method,
        public array $arguments,
        public int $acceptedArgs,
    ) {
    }
}
