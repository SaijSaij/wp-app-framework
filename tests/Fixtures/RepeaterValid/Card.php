<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RepeaterValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Item;
use WP_Comment;
use WP_Post;
use WP_Term;
use WP_User;

#[Block('card', title: 'Card', category: BlockCategory::Design, usesContext: ['postId'])]
final class Card
{
    public function render(
        #[Item]
        ?WP_Post $post,
        #[Item]
        ?Event $event,
        #[Item]
        ?WP_Term $term,
        #[Item]
        ?WP_Comment $comment,
        #[Item]
        ?WP_User $user,
        #[Item]
        ?Visit $visit,
    ): string {
        return implode(',', [
            $post?->ID,
            $event?->getId(),
            $term?->term_id,
            $comment?->comment_ID,
            $user?->ID,
            $visit?->getId(),
        ]);
    }
}
