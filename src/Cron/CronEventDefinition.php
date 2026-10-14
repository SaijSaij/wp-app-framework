<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cron;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A validated `#[CronEvent]` method.
 *
 * @phpstan-type CronArgument array{param: string, service: class-string}
 */
#[NoService]
final readonly class CronEventDefinition
{
    /**
     * @param non-empty-string              $name
     * @param non-empty-string|positive-int $interval Name of a WordPress interval (CronInterval) or seconds.
     * @param non-empty-string|null         $at       Start time `HH:MM` in the time zone of the site.
     * @param int<1, 7>|null                $weekday  ISO 8601 day of the week (Weekday).
     * @param class-string                  $class
     * @param list<CronArgument>            $arguments The services injected into the method parameters.
     */
    public function __construct(
        private string $name,
        private string|int $interval,
        private ?string $at,
        private ?int $weekday,
        private string $class,
        private string $method,
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
     * @return non-empty-string|positive-int
     */
    public function getInterval(): string|int
    {
        return $this->interval;
    }

    /**
     * @return non-empty-string|null
     */
    public function getAt(): ?string
    {
        return $this->at;
    }

    /**
     * @return int<1, 7>|null
     */
    public function getWeekday(): ?int
    {
        return $this->weekday;
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
     * @return list<CronArgument>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
