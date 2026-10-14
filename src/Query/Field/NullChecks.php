<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\Query\Condition;

/**
 * Conditions on null for the field classes of nullable columns.
 *
 * @phpstan-require-extends Field
 */
trait NullChecks
{
    /**
     * Rows whose column is null.
     */
    public function isNull(): Condition
    {
        return $this->nullCondition('IS NULL');
    }

    /**
     * Rows whose column is not null.
     */
    public function isNotNull(): Condition
    {
        return $this->nullCondition('IS NOT NULL');
    }
}
