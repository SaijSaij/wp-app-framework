<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

use Saij\WpAppFramework\DependencyInjection\NoService;
use wpdb;

/**
 * The sort order of a query that sorts by meta fields, written into the SQL clauses of its WP_Query (filter
 * posts_clauses, only while the query runs).
 *
 * WordPress' own sort order by a named meta clause cannot keep posts without a stored value in place: it joins all
 * meta rows of a post and groups them, so such a post sorts by an arbitrary row. Here each meta field gets a join on
 * exactly its key, and a post without a value sorts like the default of the field: COALESCE(value, default). MIN()
 * keeps the order deterministic if a key is stored twice for a post. Keys and defaults are quoted by
 * $wpdb->prepare(); tables, aliases, casts and columns come from WordPress and fixed lists.
 *
 * @internal
 *
 * @phpstan-type OrderSpec array{meta: non-empty-string, default: string, cast: string, direction: 'ASC'|'DESC'}|array{column: string, direction: 'ASC'|'DESC'}
 */
#[NoService]
final class MetaOrderClauses
{
    /** The query variable that holds the sort order (and makes it part of every cache key of the query). */
    public const string QUERY_VAR = 'wp_app_framework_order';

    /**
     * @param array<string, string> $clauses The clauses of posts_clauses.
     * @param list<OrderSpec>       $order
     * @return array<string, string>
     */
    public static function apply(array $clauses, array $order, wpdb $wpdb): array
    {
        $join = $clauses['join'] ?? '';
        $orderBy = [];
        foreach ($order as $index => $spec) {
            if (isset($spec['meta'])) {
                $alias = 'wpaf_order_' . $index;
                $key = $wpdb->prepare('%s', $spec['meta']);
                $default = $wpdb->prepare('%s', $spec['default']);
                $join .= " LEFT JOIN {$wpdb->postmeta} AS {$alias} ON ({$alias}.post_id = {$wpdb->posts}.ID AND {$alias}.meta_key = {$key})";
                $orderBy[] = "COALESCE(MIN(CAST({$alias}.meta_value AS {$spec['cast']})), CAST({$default} AS {$spec['cast']})) {$spec['direction']}";
                continue;
            }
            $orderBy[] = $spec['column'] === 'RAND()' ? 'RAND()' : "{$wpdb->posts}.{$spec['column']} {$spec['direction']}";
        }
        $original = $clauses['orderby'] ?? '';

        return [
            ...$clauses,
            'join' => $join,
            // One row per post; other columns of the posts table depend on its ID.
            'groupby' => ($clauses['groupby'] ?? '') !== '' ? $clauses['groupby'] : "{$wpdb->posts}.ID",
            'orderby' => implode(', ', $original === '' ? $orderBy : [...$orderBy, $original]),
        ];
    }
}
