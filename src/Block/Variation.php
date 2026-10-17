<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Block;

use Attribute;

/**
 * A variation of a block: preset attributes and inner blocks the editor
 * offers under their own title. The content stores the block itself.
 *
 * @api Attributes/Blocks
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Variation
{
    /**
     * @param string               $name        Unique within the block: lowercase letters, digits and "-".
     * @param string               $title       Title of the variation in the inserter; must not be empty.
     * @param string               $icon        Name of a Dashicon, e.g. `cover-image`; `''` for the icon of the block.
     * @param string               $description Description shown in the inserter and the block inspector.
     * @param list<string>         $keywords    Search terms for the inserter.
     * @param array<string, mixed> $attributes  Preset attribute values; backed enums stand for their value.
     * @param list<TemplateBlock>  $innerBlocks Preset inner blocks.
     * @param list<VariationScope> $scope       Where the editor offers the variation.
     * @param bool                 $isDefault   Replaces the block itself in the inserter.
     * @param list<string>         $isActive    Attributes by which the editor recognizes the variation of a block
     *                                          (its title and icon in the block card and the list view); each must
     *                                          be preset in $attributes.
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $icon = '',
        public string $description = '',
        public array $keywords = [],
        public array $attributes = [],
        public array $innerBlocks = [],
        public array $scope = [VariationScope::Inserter, VariationScope::Block],
        public bool $isDefault = false,
        public array $isActive = [],
    ) {}
}
