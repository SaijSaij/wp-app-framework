<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Field\Field;

/**
 * A condition on a field, created by the methods of the field classes: the field, the operator and the compared
 * values. The query that receives it turns it into its own form: a meta_query clause of WP_Query (PostQuery) or SQL
 * (RowQuery).
 *
 * @internal
 */
#[NoService]
final readonly class FieldCondition implements Condition
{
    /**
     * @param '='|'!='|'>'|'>='|'<'|'<='|'IN'|'NOT IN'|'BETWEEN'|'LIKE'|'IS NULL'|'IS NOT NULL' $operator
     * @param list<int|float|string>                                                         $values None for IS NULL and IS NOT NULL.
     */
    public function __construct(
        private Field $field,
        private string $operator,
        private array $values,
    ) {}

    public function getField(): Field
    {
        return $this->field;
    }

    /**
     * @return '='|'!='|'>'|'>='|'<'|'<='|'IN'|'NOT IN'|'BETWEEN'|'LIKE'|'IS NULL'|'IS NOT NULL'
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @return list<int|float|string>
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
