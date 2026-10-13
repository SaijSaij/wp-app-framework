<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use Attribute;

/**
 * Declares a post type on a subclass of AbstractPost: the class is the object of a
 * post of the type. The framework registers the type as `{namespace}_{name}`
 * (namespace of the theme or plugin with "_" instead of "-"), always shown in the
 * REST API and therefore in the block editor if it supports the editor.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class PostType
{
    /**
     * @param string                $name          Name without namespace: lowercase letters, digits and "_", starting
     *                                             with a letter; the full name has at most 20 characters.
     * @param string                $label         Plural name, e.g. "Events".
     * @param string                $singularLabel Singular name, e.g. "Event".
     * @param list<PostTypeSupport> $supports      Features of the type; `custom-fields` is added for meta.
     * @param bool                  $archive       The type has an archive page at its slug.
     * @param string|null           $slug          URL slug; null uses the name with "-" instead of "_".
     * @param string|null           $menuIcon      Dashicon class or URL of the admin menu icon.
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $singularLabel,
        public ?PostTypeLabels $labels = null,
        public string $description = '',
        public bool $public = true,
        public bool $showUi = true,
        public array $supports = [PostTypeSupport::Title, PostTypeSupport::Editor],
        public bool $hierarchical = false,
        public bool $archive = false,
        public ?string $slug = null,
        public ?string $menuIcon = null,
        public ?int $menuPosition = null,
    ) {}
}
