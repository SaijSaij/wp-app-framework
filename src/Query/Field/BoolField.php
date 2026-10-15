<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Condition;

/**
 * A bool field of a meta class or a column of a table; WordPress stores true as "1" and false as "" in meta fields,
 * a table as 1 and 0.
 *
 * @api Query fields
 */
#[NoService]
readonly class BoolField extends Field
{
    /**
     * Matches values equal to $value.
     *
     * @param bool $value True or false.
     */
    public function equals(bool $value): Condition
    {
        return $this->condition('=', [$value ? 1 : 0]);
    }

    public function getCast(): string
    {
        return 'SIGNED';
    }
}
