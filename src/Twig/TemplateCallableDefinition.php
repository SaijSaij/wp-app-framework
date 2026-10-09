<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Twig;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A validated `#[TemplateFunction]` or `#[TemplateFilter]` method.
 */
#[NoService]
final readonly class TemplateCallableDefinition
{
    /**
     * @param class-string $class
     */
    public function __construct(
        private TemplateCallableKind $kind,
        private string $name,
        private string $class,
        private string $method,
        private bool $html,
    ) {
    }

    public function getKind(): TemplateCallableKind
    {
        return $this->kind;
    }

    public function getName(): string
    {
        return $this->name;
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
     * True if the result is HTML and printed without escaping.
     */
    public function isHtml(): bool
    {
        return $this->html;
    }
}
