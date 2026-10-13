<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use Attribute;

/**
 * Declares a taxonomy on a class without constructor parameters (term meta comes
 * later). The framework registers it as `{namespace}_{name}`, always shown in the
 * REST API, so the block editor offers it for its post types.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Taxonomy
{
    /**
     * @param string                          $name          Name without namespace: lowercase letters, digits and "_",
     *                                                       starting with a letter; the full name has at most 32 characters.
     * @param list<class-string<AbstractPost>> $postTypes     Post classes of the taxonomy: classes with #[PostType], Post
     *                                                       or Page.
     * @param string|null                     $slug          URL slug; null uses the name with "-" instead of "_".
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
