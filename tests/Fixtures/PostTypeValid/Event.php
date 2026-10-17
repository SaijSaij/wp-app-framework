<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeValid;

use WebCodingCat\WpAppFramework\Post\AbstractPost;
use WebCodingCat\WpAppFramework\Post\PostType;
use WebCodingCat\WpAppFramework\Post\PostTypeLabels;
use WebCodingCat\WpAppFramework\Post\PostTypeSupport;

#[PostType(
    'event',
    label: 'Events',
    singularLabel: 'Event',
    labels: new PostTypeLabels(addNewItem: 'Neues Event'),
    supports: [PostTypeSupport::Title, PostTypeSupport::Editor, PostTypeSupport::Revisions],
    archive: true,
    menuIcon: 'dashicons-calendar',
)]
final class Event extends AbstractPost
{
    /**
     * Post classes may have methods of their own.
     */
    public function getCapacity(): int
    {
        return $this->meta(EventDetails::class)->getCapacity();
    }
}
