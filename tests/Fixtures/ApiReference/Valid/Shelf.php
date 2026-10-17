<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ApiReference\Valid;

use WebCodingCat\WpAppFramework\Twig\TemplateFunction;

/**
 * Holds widgets.
 *
 * @api Services
 */
final class Shelf
{
    /** Number of boards. */
    public const int BOARDS = 3;

    /**
     * @internal
     */
    public const string SECRET = 'x';

    /**
     * The widget of a size, or null.
     *
     * @param Size $size The size.
     *
     * @return Widget|null The first widget of the size.
     */
    public function find(Size $size): ?Widget
    {
        return $size === Size::Large ? null : new Widget('a');
    }

    /**
     * @internal Used by the framework.
     */
    public function hidden(): void {}

    /**
     * Prints the label of a size.
     *
     * @param Size|null $size The size, null for none.
     */
    #[TemplateFunction('shelf_label', html: true)]
    public function label(?Size $size = null): string
    {
        return $size === null ? '' : $size->value;
    }
}
