<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

/**
 * A comparison of a number, e.g. of the comment count.
 */
enum Comparison: string
{
    case Equal = '=';
    case NotEqual = '!=';
    case Greater = '>';
    case GreaterOrEqual = '>=';
    case Less = '<';
    case LessOrEqual = '<=';
}
