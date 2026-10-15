<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use Saij\WpAppFramework\Twig\TemplateFunction;

/**
 * Twig functions for links (`Link` values).
 */
final class LinkTemplateFunctions
{
    /**
     * The attributes of an `a` element for a link: `href` (escaped with
     * `esc_url()`) and, for a link that opens in a new tab, `target="_blank"
     * rel="noopener"`. The output is HTML and is printed unescaped. Returns
     * `''` for a link without URL (also a link to content that no longer
     * exists or is not public) or with a URL `esc_url()` rejects, so check
     * `link.url` before you print the element. Write classes and other
     * attributes next to it.
     *
     * ```twig
     * {% if attrs.cta.url %}
     *     <a class="teaser__cta" {{ link_attributes(attrs.cta) }}>More</a>
     * {% endif %}
     * ```
     *
     * @param Link $link The link, e.g. a `Link` attribute of the block.
     */
    #[TemplateFunction('link_attributes', html: true)]
    public function linkAttributes(Link $link): string
    {
        $url = $link->url === '' ? '' : esc_url($link->url);
        if ($url === '') {
            return '';
        }

        return 'href="' . $url . '"' . ($link->opensInNewTab ? ' target="_blank" rel="noopener"' : '');
    }
}
