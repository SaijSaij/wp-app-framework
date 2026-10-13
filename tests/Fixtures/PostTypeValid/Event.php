<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeValid;

use Saij\WpAppFramework\Post\AbstractPost;
use Saij\WpAppFramework\Post\PostType;
use Saij\WpAppFramework\Post\PostTypeLabels;
use Saij\WpAppFramework\Post\PostTypeSupport;

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
