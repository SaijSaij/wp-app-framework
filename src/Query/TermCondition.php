<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

use InvalidArgumentException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Post\PostRegistry;
use WP_Term;

/**
 * A condition on the terms of a taxonomy, created by Terms.
 *
 * @internal
 */
#[NoService]
final readonly class TermCondition implements Condition
{
    /**
     * @param class-string             $taxonomy
     * @param 'IN'|'NOT IN'|'AND'|'EXISTS'|'NOT EXISTS' $operator
     * @param list<WP_Term|int>        $terms
     */
    public function __construct(
        private string $taxonomy,
        private string $operator,
        private array $terms,
        private bool $includeChildren,
    ) {}

    /**
     * The name of the taxonomy.
     */
    public function getTaxonomyName(): string
    {
        return PostRegistry::taxonomyName($this->taxonomy);
    }

    /**
     * The clause of a tax_query.
     *
     * @return array<string, mixed>
     */
    public function clause(): array
    {
        $name = $this->getTaxonomyName();
        $clause = ['taxonomy' => $name, 'operator' => $this->operator, 'include_children' => $this->includeChildren];
        if ($this->operator === 'EXISTS' || $this->operator === 'NOT EXISTS') {
            return $clause;
        }

        $ids = [];
        foreach ($this->terms as $term) {
            if ($term instanceof WP_Term && $term->taxonomy !== $name) {
                throw new InvalidArgumentException(sprintf('Terms of %s: the term %d belongs to the taxonomy %s, not to %s.', $this->taxonomy, $term->term_id, $term->taxonomy, $name));
            }
            $ids[] = $term instanceof WP_Term ? $term->term_id : $term;
        }

        return [...$clause, 'field' => 'term_id', 'terms' => $ids];
    }
}
