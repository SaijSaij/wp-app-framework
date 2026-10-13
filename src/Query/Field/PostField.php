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
 */
#[NoService]
final readonly class PostField extends Field
{
    /**
     * @param class-string    $metaClass The meta class (#[PostMeta]).
     * @param string          $field     The name of the constructor parameter.
     * @param class-string<P> $postClass The post class of the field.
     */
    public function __construct(string $metaClass, string $field, private string $postClass)
    {
        parent::__construct($metaClass, $field);
    }

    /**
     * @param P|int $post
     */
    public function equals(AbstractPost|int $post): Condition
    {
        return $this->condition('=', [$this->id($post)]);
    }

    /**
     * @param P|int ...$posts
     */
    public function in(AbstractPost|int ...$posts): Condition
    {
        return $this->condition('IN', array_map($this->id(...), array_values($posts)));
    }

    /**
     * @param P|int ...$posts
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
            throw new InvalidArgumentException(sprintf('%s::$%s refers to a %s, %s given.', $this->getMetaClass(), $this->getField(), $this->postClass, $post::class));
        }

        return $post->getId() ?? throw new InvalidArgumentException(sprintf('%s::$%s: the %s has not been saved yet.', $this->getMetaClass(), $this->getField(), $post::class));
    }
}
