<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Labels of a post type beyond its name and singular name. A label left at null is
 * derived from the singular and plural name in English ("Add New Event"); WordPress
 * would otherwise show the labels of posts.
 */
#[NoService]
final readonly class PostTypeLabels
{
    public function __construct(
        public ?string $addNew = null,
        public ?string $addNewItem = null,
        public ?string $editItem = null,
        public ?string $newItem = null,
        public ?string $viewItem = null,
        public ?string $viewItems = null,
        public ?string $searchItems = null,
        public ?string $notFound = null,
        public ?string $notFoundInTrash = null,
        public ?string $parentItemColon = null,
        public ?string $allItems = null,
        public ?string $archives = null,
        public ?string $attributes = null,
        public ?string $menuName = null,
        public ?string $itemPublished = null,
        public ?string $itemUpdated = null,
    ) {}
}
