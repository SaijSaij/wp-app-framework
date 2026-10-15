<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Labels of a taxonomy beyond its name and singular name. A label left at null is
 * derived from the singular and plural name in English ("Add New Genre"); WordPress
 * would otherwise show the labels of tags or categories. The defaults below are those of
 * a taxonomy with the singular name "Genre" and the plural name "Genres".
 *
 * @api Posts and queries
 */
#[NoService]
final readonly class TaxonomyLabels
{
    /**
     * @param string|null $searchItems Button that searches terms; default "Search Genres".
     * @param string|null $allItems    Entry for all terms, e.g. in the filter of the list of posts; default "All Genres".
     * @param string|null $parentItem  Field of the parent term (hierarchical taxonomies only); default "Parent Genre".
     * @param string|null $editItem    Heading of the screen that edits a term; default "Edit Genre".
     * @param string|null $viewItem    Link that views the archive of a term; default "View Genre".
     * @param string|null $updateItem  Button that saves a term; default "Update Genre".
     * @param string|null $addNewItem  Heading and button that add a term; default "Add New Genre".
     * @param string|null $newItemName Field of the name of a new term; default "New Genre Name".
     * @param string|null $notFound    Message when no terms are found; default "No genres found.".
     * @param string|null $menuName    Admin menu entry; default the plural name, "Genres".
     * @param string|null $backToItems Link back to the list shown after a term was updated; default "← Go to Genres".
     */
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
