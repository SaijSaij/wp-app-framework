<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use InvalidArgumentException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Post\AbstractPost;
use Saij\WpAppFramework\Query\Condition;

/**
 * A field of a meta class that refers to a post of a post class (`?Venue`), stored as its ID; no post is stored as 0
 * or not at all.
 *
 * @template P of AbstractPost
 *
 * @api Query fields
 */
#[NoService]
final readonly class PostField extends Field
{
    /**
     * @param class-string    $class     The class the field belongs to: the meta class (#[PostMeta]).
     * @param string          $field     The name of the constructor parameter.
     * @param class-string<P> $postClass The post class of the field.
     */
    public function __construct(string $class, string $field, private string $postClass)
    {
        parent::__construct($class, $field);
    }

    /**
     * Matches posts whose field refers to $post. Throws an InvalidArgumentException for a post of another post class or
     * a post that has not been saved yet.
     *
     * @param P|int $post A post of the post class of the field or its ID.
     */
    public function equals(AbstractPost|int $post): Condition
    {
        return $this->condition('=', [$this->id($post)]);
    }

    /**
     * Matches posts whose field refers to one of $posts. Throws an InvalidArgumentException for a post of another post
     * class or a post that has not been saved yet, or if no post is given.
     *
     * @param P|int ...$posts Posts of the post class of the field or their IDs.
     */
    public function in(AbstractPost|int ...$posts): Condition
    {
        return $this->condition('IN', array_map($this->id(...), array_values($posts)));
    }

    /**
     * Matches posts whose field refers to none of $posts, including posts without a post in the field. Throws an
     * InvalidArgumentException for a post of another post class or a post that has not been saved yet, or if no post is
     * given.
     *
     * @param P|int ...$posts Posts of the post class of the field or their IDs.
     */
    public function notIn(AbstractPost|int ...$posts): Condition
    {
        return $this->condition('NOT IN', array_map($this->id(...), array_values($posts)));
    }

    /**
     * Posts without a post in the field.
     */
    public function isNull(): Condition
    {
        return $this->condition('=', [0]);
    }

    /**
     * Posts with a post in the field.
     */
    public function isNotNull(): Condition
    {
        return $this->condition('!=', [0]);
    }

    /**
     * @return class-string<P>
     */
    public function getTypeClass(): string
    {
        return $this->postClass;
    }

    public function getCast(): string
    {
        return 'SIGNED';
    }

    private function id(AbstractPost|int $post): int
    {
        if (is_int($post)) {
            return $post;
        }
        if (!$post instanceof $this->postClass) {
            throw new InvalidArgumentException(sprintf('%s::$%s refers to a %s, %s given.', $this->getClass(), $this->getField(), $this->postClass, $post::class));
        }

        return $post->getId() ?? throw new InvalidArgumentException(sprintf('%s::$%s: the %s has not been saved yet.', $this->getClass(), $this->getField(), $post::class));
    }
}
