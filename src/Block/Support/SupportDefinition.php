<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block\Support;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Value\ValueFactory;
use Saij\WpAppFramework\Value\ValueSchemaBuilder;

/**
 * A validated `#[BlockSupport]` class.
 *
 * @phpstan-import-type Value from ValueFactory
 * @phpstan-import-type Control from ValueSchemaBuilder
 */
#[NoService]
final readonly class SupportDefinition
{
    /**
     * @param class-string         $class
     * @param array<string, mixed> $schema  Type keywords of the attribute schema, without default.
     * @param Value                $value   Description of the value for the value factory.
     * @param Control              $control Inspector control, with the label of the support.
     * @param mixed                $default Default of className()'s parameter, as PHP value.
     * @param string|null          $editorScript Editor script next to the class below the script directory,
     *                                           without extension.
     */
    public function __construct(
        private string $class,
        private string $attribute,
        private SupportGroup $group,
        private array $schema,
        private array $value,
        private array $control,
        private mixed $default,
        private ?string $editorScript = null,
    ) {}

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    public function getGroup(): SupportGroup
    {
        return $this->group;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * @return Value
     */
    public function getValue(): array
    {
        return $this->value;
    }

    /**
     * @return Control
     */
    public function getControl(): array
    {
        return $this->control;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getEditorScript(): ?string
    {
        return $this->editorScript;
    }
}
