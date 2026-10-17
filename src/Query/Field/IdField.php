<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Query\Field;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Query\Condition;

/**
 * An ID field of a meta class: an image (#[Image]) or a reference to a post, term or user by ID (`?int`). No object
 * is stored as 0 or not at all.
 *
 * @api Query fields
 */
#[NoService]
final readonly class IdField extends Field
{
    /**
     * Matches posts whose field holds the object with ID $id.
     *
     * @param int $id The ID of the object.
     */
    public function equals(int $id): Condition
    {
        return $this->condition('=', [$id]);
    }

    /**
     * Matches posts whose field holds one of the objects. Throws an InvalidArgumentException if no value is given.
     *
     * @param int ...$ids The IDs of the objects.
     */
    public function in(int ...$ids): Condition
    {
        return $this->condition('IN', array_values($ids));
    }

    /**
     * Matches posts whose field holds none of the objects, including posts without an object. Throws an
     * InvalidArgumentException if no value is given.
     *
     * @param int ...$ids The IDs of the objects.
     */
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
