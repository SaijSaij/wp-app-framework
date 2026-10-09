<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditor;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Editor\EditorTemplateFunctions;

#[Block('fixture/teaser', title: 'Teaser', category: BlockCategory::Text)]
final readonly class Teaser
{
    public function __construct(private EditorTemplateFunctions $editor)
    {
    }

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
