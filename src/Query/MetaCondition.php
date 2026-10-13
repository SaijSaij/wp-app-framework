<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Field\Field;

/**
 * A condition on a meta field, created by the methods of the field classes.
 *
 * @internal
 */
#[NoService]
final readonly class MetaCondition implements Condition
{
    /**
     * @param '='|'!='|'>'|'>='|'<'|'<='|'IN'|'NOT IN'|'BETWEEN'|'LIKE' $operator
     * @param non-empty-list<int|float|string>                           $values
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
     * The clause of a meta_query; null and a NOT EXISTS clause in an OR group if the default matches: a meta value
     * that is not stored is its default.
     *
     * @param non-empty-string $key
     * @return array<string|int, mixed>
     */
    public function clause(string $key, mixed $default): array
    {
        $clause = [
            'key' => $key,
            'compare' => $this->operator,
            'value' => in_array($this->operator, ['IN', 'NOT IN', 'BETWEEN'], true) ? $this->values : $this->values[0],
            'type' => $this->field->getCast(),
        ];
        if (!$this->matches($default)) {
            return $clause;
        }

        return ['relation' => 'OR', $clause, ['key' => $key, 'compare' => 'NOT EXISTS']];
    }

    /**
     * Whether a stored value meets the condition, compared like the database does after the cast (strings without
     * regard to case, like the default collations).
     */
    public function matches(mixed $stored): bool
    {
        $value = $this->field->normalize($stored);
        $values = array_map($this->field->normalize(...), $this->values);

        return match ($this->operator) {
            '=' => $value === $values[0],
            '!=' => $value !== $values[0],
            '>' => $value > $values[0],
            '>=' => $value >= $values[0],
            '<' => $value < $values[0],
            '<=' => $value <= $values[0],
            'IN' => in_array($value, $values, true),
            'NOT IN' => !in_array($value, $values, true),
            'BETWEEN' => $value >= $values[0] && $value <= ($values[1] ?? $values[0]),
            'LIKE' => str_contains((string) $value, (string) $values[0]),
        };
    }
}
