<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Labels of a taxonomy beyond its name and singular name. A label left at null is
 * derived from the singular and plural name in English ("Add New Genre"); WordPress
 * would otherwise show the labels of tags or categories.
 */
#[NoService]
final readonly class TaxonomyLabels
{
    public function __construct(
        public ?string $searchItems = null,
        public ?string $allItems = null,
        public ?string $parentItem = null,
        public ?string $editItem = null,
        public ?string $viewItem = null,
        public ?string $updateItem = null,
        public ?string $addNewItem = null,
        public ?string $newItemName = null,
        public ?string $notFound = null,
        public ?string $menuName = null,
        public ?string $backToItems = null,
    ) {}
}
