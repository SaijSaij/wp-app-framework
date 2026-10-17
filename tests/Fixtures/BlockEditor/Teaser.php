<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockEditor;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Editor\EditorTemplateFunctions;

#[Block('teaser', title: 'Teaser', category: BlockCategory::Text)]
final readonly class Teaser
{
    public function __construct(private EditorTemplateFunctions $editor) {}

    public function render(TeaserAttributes $attrs): string
    {
        $ctas = '';
        foreach ($attrs->ctas as $index => $cta) {
            $ctas .= '<a' . $this->editor->field(['ctas', $index, 'label']) . '>' . esc_attr($cta->label) . '</a>';
        }

        return '<div><p' . $this->editor->field('kicker') . '>' . esc_attr($attrs->kicker) . '</p>'
            . ($this->editor->isEditor() ? '<i>editor</i>' : '')
            . $ctas . '</div>';
    }
}
