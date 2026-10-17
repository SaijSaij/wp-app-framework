<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Post;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Labels of a post type beyond its name and singular name. A label left at null is
 * derived from the singular and plural name in English ("Add New Event"); WordPress
 * would otherwise show the labels of posts. The defaults below are those of a post type
 * with the singular name "Event" and the plural name "Events".
 *
 * @api Posts and queries
 */
#[NoService]
final readonly class PostTypeLabels
{
    /**
     * @param string|null $addNew          Button that adds a post; default "Add New Event".
     * @param string|null $addNewItem      Heading of the screen that adds a post; default "Add New Event".
     * @param string|null $editItem        Heading of the edit screen; default "Edit Event".
     * @param string|null $newItem         Title of the page of a new post; default "New Event".
     * @param string|null $viewItem        Link that views a post; default "View Event".
     * @param string|null $viewItems       Link that views the archive of the type; default "View Events".
     * @param string|null $searchItems     Button that searches posts; default "Search Events".
     * @param string|null $notFound        Message when no posts are found; default "No events found.".
     * @param string|null $notFoundInTrash Message of an empty trash; default "No events found in Trash.".
     * @param string|null $parentItemColon Prefix of the parent post (hierarchical post types); default "Parent Event:".
     * @param string|null $allItems        Admin submenu entry of the list of posts; default "All Events".
     * @param string|null $archives        The archive in the navigation menu editor; default "Event Archives".
     * @param string|null $attributes      Title of the page attributes box; default "Event Attributes".
     * @param string|null $menuName        Admin menu entry; default the plural name, "Events".
     * @param string|null $itemPublished   Notice after a post was published; default "Event published.".
     * @param string|null $itemUpdated     Notice after a post was updated; default "Event updated.".
     */
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
