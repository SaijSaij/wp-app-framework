<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Condition;

/**
 * An ID field of a meta class: an image (#[Image]) or a reference to a post, term or user by ID (`?int`). No object
 * is stored as 0 or not at all.
 */
#[NoService]
final readonly class IdField extends Field
{
    public function equals(int $id): Condition
    {
        return $this->condition('=', [$id]);
    }

    public function in(int ...$ids): Condition
    {
        return $this->condition('IN', array_values($ids));
    }

    public function notIn(int ...$ids): Condition
    {
        return $this->condition('NOT IN', array_values($ids));
    }

    /**
     * Posts without an object in the field.
     */
    public function isNull(): Condition
    {
        return $this->condition('=', [0]);
    }

    /**
     * Posts with an object in the field.
     */
    public function isNotNull(): Condition
    {
        return $this->condition('!=', [0]);
    }

    public function getCast(): string
    {
        return 'SIGNED';
    }
}
