<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

use InvalidArgumentException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Table\AbstractRow;

/**
 * Conditions joined with OR (Any) or AND (All). A group holds conditions of one kind: on meta fields, on terms or on
 * the columns of a table. WordPress keeps conditions on meta fields and on terms apart and joins the two with AND;
 * columns belong to queries of rows.
 */
#[NoService]
abstract readonly class Group implements Condition
{
    /** @var 'meta'|'terms'|'row' */
    private string $kind;

    /**
     * @param list<Condition> $conditions
     */
    final protected function __construct(
        private array $conditions,
    ) {
        if ($conditions === []) {
            throw new InvalidArgumentException(sprintf('%s::of() needs at least one condition.', static::class));
        }
        $kinds = array_values(array_unique(array_map(self::kindOf(...), $conditions)));
        if (count($kinds) > 1) {
            throw new InvalidArgumentException(sprintf(
                '%s::of() mixes conditions of different kinds (meta fields, terms, columns of a table); a group holds conditions of one kind.',
                static::class,
            ));
        }
        $this->kind = $kinds[0];
    }

    /**
     * @return list<Condition>
     *
     * @internal
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * @return 'meta'|'terms'|'row'
     *
     * @internal
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * @return 'OR'|'AND'
     *
     * @internal
     */
    abstract public function getRelation(): string;

    /**
     * @internal What a condition is about: meta fields, terms or the columns of a table (rows).
     *
     * @return 'meta'|'terms'|'row'
     */
    public static function kindOf(Condition $condition): string
    {
        return match (true) {
            $condition instanceof FieldCondition => is_subclass_of($condition->getField()->getClass(), AbstractRow::class) ? 'row' : 'meta',
            $condition instanceof TermCondition => 'terms',
            $condition instanceof self => $condition->getKind(),
            default => throw new InvalidArgumentException(sprintf(
                '%s is no condition of the framework; conditions come from fields, Terms, Any and All.',
                $condition::class,
            )),
        };
    }
}
