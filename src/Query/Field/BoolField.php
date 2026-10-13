<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Condition;

/**
 * A bool field of a meta class; WordPress stores true as "1" and false as "".
 */
#[NoService]
final readonly class BoolField extends Field
{
    public function equals(bool $value): Condition
    {
        return $this->condition('=', [$value ? 1 : 0]);
    }

    public function getCast(): string
    {
        return 'SIGNED';
    }
}
