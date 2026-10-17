<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cron;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * A validated `#[CronTask]` method.
 *
 * @phpstan-import-type CronArgument from CronEventDefinition
 */
#[NoService]
final readonly class CronTaskDefinition
{
    /**
     * @param non-empty-string   $name      Short name of the task class in kebab case.
     * @param class-string       $task      The task (value) class.
     * @param class-string       $class     The service with the handler.
     * @param string             $param     The parameter that receives the task.
     * @param list<CronArgument> $arguments The services injected into the further parameters.
     */
    public function __construct(
        private string $name,
        private string $task,
        private string $class,
        private string $method,
        private string $param,
        private array $arguments,
    ) {}

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return class-string
     */
    public function getTask(): string
    {
        return $this->task;
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

    public function getParam(): string
    {
        return $this->param;
    }

    /**
     * @return list<CronArgument>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
