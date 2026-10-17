<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cache;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Names of the classes tags may name instead of a string: settings groups
 * (SettingsTag), post classes (PostTypeTag) and taxonomy classes (TaxonomyTag)
 * of the instance.
 *
 * @internal
 */
#[NoService]
final readonly class TagNames
{
    /**
     * @param array<class-string, string> $settingsGroups Group name by class.
     * @param array<class-string, string> $postTypes      Post type by post class (#[PostType], Post, Page).
     * @param array<class-string, string> $taxonomies     Taxonomy by class (#[Taxonomy]).
     */
    public function __construct(
        private array $settingsGroups = [],
        private array $postTypes = [],
        private array $taxonomies = [],
    ) {}

    public function settingsGroup(string $class): ?string
    {
        return $this->settingsGroups[$class] ?? null;
    }

    public function postType(string $class): ?string
    {
        return $this->postTypes[$class] ?? null;
    }

    public function taxonomy(string $class): ?string
    {
        return $this->taxonomies[$class] ?? null;
    }
}
