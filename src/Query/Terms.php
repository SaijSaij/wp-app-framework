<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Query;

use InvalidArgumentException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WP_Term;

/**
 * Conditions on the terms of a taxonomy, named by its class (#[Taxonomy]):
 * `Terms::in(Genre::class, [$jazz, $blues])`. Terms are WP_Term objects or term IDs.
 *
 * @api Posts and queries
 */
#[NoService]
final class Terms
{
    /**
     * Posts with at least one of the terms; with their child terms by default, like WordPress.
     *
     * @param class-string      $taxonomy        Taxonomy class: a class with #[Taxonomy], Category or Tag.
     * @param list<WP_Term|int> $terms           Terms as objects or IDs; at least one.
     * @param bool              $includeChildren The child terms of the terms count as well (hierarchical taxonomies).
     */
    public static function in(string $taxonomy, array $terms, bool $includeChildren = true): Condition
    {
        return new TermCondition($taxonomy, 'IN', self::terms('in', $terms), $includeChildren);
    }

    /**
     * Posts with none of the terms.
     *
     * @param class-string      $taxonomy        Taxonomy class: a class with #[Taxonomy], Category or Tag.
     * @param list<WP_Term|int> $terms           Terms as objects or IDs; at least one.
     * @param bool              $includeChildren The child terms of the terms count as well (hierarchical taxonomies).
     */
    public static function notIn(string $taxonomy, array $terms, bool $includeChildren = true): Condition
    {
        return new TermCondition($taxonomy, 'NOT IN', self::terms('notIn', $terms), $includeChildren);
    }

    /**
     * Posts with all of the terms.
     *
     * @param class-string      $taxonomy        Taxonomy class: a class with #[Taxonomy], Category or Tag.
     * @param list<WP_Term|int> $terms           Terms as objects or IDs; at least one.
     * @param bool              $includeChildren The child terms of the terms count as well (hierarchical taxonomies).
     */
    public static function all(string $taxonomy, array $terms, bool $includeChildren = true): Condition
    {
        return new TermCondition($taxonomy, 'AND', self::terms('all', $terms), $includeChildren);
    }

    /**
     * Posts with any term of the taxonomy.
     *
     * @param class-string $taxonomy Taxonomy class: a class with #[Taxonomy], Category or Tag.
     */
    public static function exists(string $taxonomy): Condition
    {
        return new TermCondition($taxonomy, 'EXISTS', [], true);
    }

    /**
     * Posts without a term of the taxonomy.
     *
     * @param class-string $taxonomy Taxonomy class: a class with #[Taxonomy], Category or Tag.
     */
    public static function notExists(string $taxonomy): Condition
    {
        return new TermCondition($taxonomy, 'NOT EXISTS', [], true);
    }

    /**
     * @param list<WP_Term|int> $terms
     * @return list<WP_Term|int>
     */
    private static function terms(string $method, array $terms): array
    {
        if ($terms === []) {
            throw new InvalidArgumentException(sprintf('Terms::%s() needs at least one term.', $method));
        }

        return $terms;
    }
}
