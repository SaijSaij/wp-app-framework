<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use Attribute;

/**
 * Declares a taxonomy on a class without constructor parameters (term meta comes
 * later). The framework registers it as `{namespace}_{name}`, always shown in the
 * REST API, so the block editor offers it for its post types.
 *
 * @api Attributes/Posts
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Taxonomy
{
    /**
     * @param string                           $name          Name without namespace: lowercase letters, digits and "_",
     *                                                        starting with a letter; the full name has at most 32
     *                                                        characters.
     * @param string                           $label         Plural name, e.g. "Genres".
     * @param string                           $singularLabel Singular name, e.g. "Genre".
     * @param list<class-string<AbstractPost>> $postTypes     Post classes of the taxonomy: classes with #[PostType], Post
     *                                                        or Page.
     * @param TaxonomyLabels|null              $labels        Further labels; null derives all of them from the names
     *                                                        in English.
     * @param string                           $description   Description of the taxonomy.
     * @param bool                             $public        The taxonomy is shown on the front end (archives of its
     *                                                        terms) and in the admin; WordPress' `public` argument.
     * @param bool                             $showUi        The admin shows screens to manage its terms.
     * @param bool                             $hierarchical  Terms have parents, like categories; otherwise flat, like
     *                                                        tags.
     * @param string|null                      $slug          URL slug; null uses the name with "-" instead of "_".
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $singularLabel,
        public array $postTypes,
        public ?TaxonomyLabels $labels = null,
        public string $description = '',
        public bool $public = true,
        public bool $showUi = true,
        public bool $hierarchical = false,
        public ?string $slug = null,
    ) {}
}
