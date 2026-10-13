<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

use InvalidArgumentException;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Conditions joined with OR (Any) or AND (All). WordPress keeps conditions on meta fields and on terms apart and
 * joins the two with AND, so a group holds conditions of one kind.
 */
#[NoService]
abstract readonly class Group implements Condition
{
    /** @var 'meta'|'terms' */
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
                '%s::of() mixes conditions on meta fields and on terms; WordPress joins the two with AND, so a group holds conditions of one kind.',
                static::class,
            ));
        }
        $this->kind = $kinds[0];
    }

    /**
     * @return list<Condition>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * @return 'meta'|'terms'
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * @return 'OR'|'AND'
     */
    abstract public function getRelation(): string;

    /**
     * @internal What a condition is about.
     *
     * @return 'meta'|'terms'
     */
    public static function kindOf(Condition $condition): string
    {
        return match (true) {
            $condition instanceof MetaCondition => 'meta',
            $condition instanceof TermCondition => 'terms',
            $condition instanceof self => $condition->getKind(),
            default => throw new InvalidArgumentException(sprintf(
                '%s is no condition of the framework; conditions come from meta fields, Terms, Any and All.',
                $condition::class,
            )),
        };
    }
}
