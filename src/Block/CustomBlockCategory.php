<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

/**
 * Implemented by a string backed enum whose cases are block categories of the
 * theme or plugin: `#[Block(category: Category::Sections)]`. The build calls
 * label() and icon() for every case and registers each case as category
 * `{namespace}-{value}`, before the categories of WordPress.
 */
interface CustomBlockCategory
{
    /**
     * Title of the category in the inserter.
     */
    public function label(): string;

    /**
     * Name of a dashicon shown next to the title in the inserter, e.g. "layout"; null for none.
     */
    public function icon(): ?string;
}
