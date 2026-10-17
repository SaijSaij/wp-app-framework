<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Hook;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * A validated `#[Hook]` method.
 *
 * @phpstan-type HookArgument array{param: string, service: class-string}|array{param: string, index: int}
 */
#[NoService]
final readonly class HookDefinition
{
    /**
     * @param class-string       $class
     * @param list<HookArgument> $arguments Method parameters in declaration order: injected service or index of the WordPress argument.
     */
    public function __construct(
        private string $name,
        private int $priority,
        private bool $filter,
        private string $class,
        private string $method,
        private array $arguments,
        private int $acceptedArgs,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * True for a filter (non-void return type), false for an action.
     */
    public function isFilter(): bool
    {
        return $this->filter;
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return list<HookArgument>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getAcceptedArgs(): int
    {
        return $this->acceptedArgs;
    }
}
