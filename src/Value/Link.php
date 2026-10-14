<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A link: a URL entered freely or a post, term or media file chosen in a
 * search, plus whether it opens in a new tab. Edited with the link control of
 * the block editor (a search form on the settings page and in classic meta
 * boxes); in blocks it counts as content. Never null: an empty URL means no
 * link. Templates print it with `link_attributes()`.
 *
 * A linked object is stored with its ID; when the value is created, the URL
 * becomes the current URL of the object, or empty if the object no longer
 * exists or is not public (renders for the block editor keep objects that are
 * not public, so editors see their choice). The stored URL of a linked object
 * is only what the editor showed when it was chosen.
 */
#[NoService]
final readonly class Link
{
    /**
     * @param string   $url           The URL; for linked objects resolved when the value is created.
     * @param bool     $opensInNewTab Open the link in a new tab (`target="_blank" rel="noopener"`).
     * @param LinkKind $kind          A free URL or the kind of the linked object.
     * @param int      $id            ID of the linked object; 0 for a free URL.
     */
    public function __construct(
        #[Url]
        public string $url = '',
        public bool $opensInNewTab = false,
        public LinkKind $kind = LinkKind::Url,
        public int $id = 0,
    ) {}
}
