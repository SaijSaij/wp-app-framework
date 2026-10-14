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
     * The attributes of an `<a>` element for a link: `href` (escaped with
     * esc_url()) and, for a link that opens in a new tab, `target="_blank"
     * rel="noopener"`. Empty for a link without URL (also a linked object that
     * no longer exists or is not public) or with a URL esc_url() rejects.
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
