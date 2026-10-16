<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Post;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Post\Page;
use Saij\WpAppFramework\Post\Post;
use Saij\WpAppFramework\Post\PostRegistry;
use Saij\WpAppFramework\Post\PostStatus;
use Saij\WpAppFramework\Post\PostTemplateFunctions;
use Saij\WpAppFramework\Post\PostTypeRegistrar;
use Saij\WpAppFramework\Post\PostValidationException;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\Density;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\Event;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\EventDetails;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\Ticket;
use Saij\WpAppFramework\Tests\Fixtures\PostTypeValid\Venue;
use Saij\WpAppFramework\Tests\Support\Posts;
use Saij\WpAppFramework\Value\ValueFactory;

/**
 * Post objects: loading, meta objects, saving and the post() template function.
 */
final class AbstractPostTest extends TestCase
{
    private string $log;

    private string|false $previousLog;

    protected function setUp(): void
    {
        [$store] = Posts::store();
        // Meta registered like on init: the registered sanitize callback filters rich text.
        new PostTypeRegistrar($store, 'my_theme_rewrite', null)->register();
        $this->log = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousLog = ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        unlink($this->log);
        Posts::reset();
        unset($GLOBALS['wp_app_framework_test_caps']);
    }

    public function testGetAndFindLoadPostsOfTheirTypeOnly(): void
    {
        Posts::add(10, 'fixture_event', ['post_title' => 'Concert', 'post_status' => 'draft', 'post_author' => '3', 'post_date_gmt' => '2026-10-01 18:00:00']);
        Posts::add(11, 'post');

        $event = Event::get(10);
        self::assertSame([10, 'Concert', PostStatus::Draft, 3], [$event->getId(), $event->getTitle(), $event->getStatus(), $event->getAuthorId()]);
        self::assertSame('2026-10-01T18:00:00+00:00', $event->getDate()?->format(DATE_ATOM));
        self::assertNull(Event::find(11));
        self::assertNull(Event::find(99));
        self::assertInstanceOf(Post::class, Post::get(11));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('There is no %s with the ID 11 (post type fixture_event).', Event::class));
        Event::get(11);
    }

    public function testMetaObjectsAreCreatedFromTheStoredValuesWithPrivateFields(): void
    {
        Posts::add(10, 'fixture_event');
        Posts::add(20, 'fixture_venue', ['post_status' => 'publish']);
        Posts::add(21, 'fixture_venue', ['post_status' => 'draft']);
        // Stored as strings, like WordPress stores scalars.
        Posts::addMeta(10, [
            'fixture_capacity' => '120',
            'fixture_venue' => '20',
            'fixture_tickets' => [['label' => 'Early', 'price' => 9.5]],
            'fixture_density' => 'compact',
            'fixture_alternatives' => [21, 20, 999],
            'fixture_photo' => '0',
        ]);

        $event = Event::get(10);
        $details = $event->meta(EventDetails::class);

        self::assertSame($details, $event->meta(EventDetails::class), 'The same object per post object.');
        self::assertSame(120, $details->getCapacity());
        self::assertSame(120, $event->getCapacity());
        self::assertInstanceOf(Venue::class, $details->getVenue());
        self::assertSame(20, $details->getVenue()->getId());
        self::assertEquals([new Ticket('Early', 9.5)], $details->getTickets());
        self::assertSame(Density::Compact, $details->getDensity());
        // Published posts only; a missing post is left out.
        self::assertSame([20], array_map(static fn(Venue $venue): ?int => $venue->getId(), $details->getAlternatives()));
        self::assertNull($details->getPhoto());
        self::assertSame('', $details->getStartDate());
    }

    public function testInvalidStoredValuesFallBackToTheirDefaultAndAreLogged(): void
    {
        Posts::add(10, 'fixture_event');
        Posts::addMeta(10, ['fixture_density' => 'huge', 'fixture_capacity' => '7']);

        $details = Event::get(10)->meta(EventDetails::class);

        self::assertSame(Density::Normal, $details->getDensity());
        self::assertSame(7, $details->getCapacity());
        self::assertStringContainsString(
            sprintf('wp-app-framework: post meta: the stored value of fixture_density of post 10 is invalid (fixture_density is not one of compact, normal.); %s::$density uses its default.', EventDetails::class),
            (string) file_get_contents($this->log),
        );
    }

    public function testSaveWritesTheChangedPostDataAndMetaOnly(): void
    {
        Posts::add(10, 'fixture_event', ['post_title' => 'Concert']);
        Posts::add(20, 'fixture_venue', ['post_status' => 'publish']);
        Posts::addMeta(10, ['fixture_capacity' => '5', 'fixture_start_date' => '2026-11-01']);

        $event = Event::get(10);
        $details = $event->meta(EventDetails::class);
        $details->setCapacity(20);
        $details->setVenue(Venue::get(20));
        $details->setDensity(Density::Compact);
        $details->setTickets([new Ticket('Door', 12.0)]);
        $event->setTitle('Concert "live"');
        // Changed by another request since loading: not written back.
        Posts::addMeta(10, ['fixture_start_date' => '2026-12-24']);
        $event->save();

        self::assertSame('Concert "live"', Event::get(10)->getTitle());
        $meta = Posts::meta(10);
        self::assertSame(20, $meta['fixture_capacity']);
        self::assertSame(20, $meta['fixture_venue']);
        self::assertSame('compact', $meta['fixture_density']);
        self::assertSame([['label' => 'Door', 'price' => 12.0, 'note' => null]], $meta['fixture_tickets']);
        self::assertSame('2026-12-24', $meta['fixture_start_date']);
        self::assertArrayNotHasKey('fixture_note', $meta);

        $details->setVenue(null);
        $event->save();
        self::assertArrayNotHasKey('fixture_venue', Posts::meta(10), 'A missing reference deletes the key.');
    }

    public function testNewPostsAreInsertedAsDraftsWithAllMetaValues(): void
    {
        $event = new Event();
        self::assertNull($event->getId());
        self::assertSame(PostStatus::Draft, $event->getStatus());
        $event->setTitle('New');
        $event->meta(EventDetails::class)->setNote('<b>Hi</b><script>alert(1)</script>');
        $event->setTerms(PostTypeValid\Genre::class, [5]);
        Posts::addTerm(5, 'fixture_genre', 'Jazz');
        $event->save();

        $id = $event->getId();
        self::assertIsInt($id);
        $post = get_post($id);
        self::assertSame(['fixture_event', 'draft', 'New'], [$post?->post_type, $post?->post_status, $post?->post_title]);
        // All fields of a new post, rich text filtered for users without unfiltered_html.
        self::assertSame(['fixture_capacity', 'fixture_start_date', 'fixture_tickets', 'fixture_density', 'fixture_note', 'fixture_alternatives', 'fixture_registration'], array_keys(Posts::meta($id)));
        self::assertSame('<b>Hi</b>', Posts::meta($id)['fixture_note']);
        self::assertSame(['Jazz'], array_map(static fn(\WP_Term $term): string => $term->name, $event->getTerms(PostTypeValid\Genre::class)));
    }

    public function testInvalidValuesWriteNothing(): void
    {
        Posts::add(10, 'fixture_event', ['post_title' => 'Old']);
        $event = Event::get(10);
        $event->setTitle('New');
        $event->meta(EventDetails::class)->setTickets([new Ticket('')]);
        // A reference to a post that has not been saved.
        $event->meta(EventDetails::class)->setVenue(new Venue());

        try {
            $event->save();
            self::fail('PostValidationException expected.');
        } catch (PostValidationException $exception) {
            self::assertSame([sprintf('%s::$venue refers to a post that has not been saved yet; save it first.', EventDetails::class)], $exception->getErrors());
        }
        self::assertSame('Old', Event::get(10)->getTitle());
        self::assertSame([], Posts::meta(10));
    }

    public function testMetaOfAnotherPostClassIsAnError(): void
    {
        Posts::add(11, 'post');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('%s is no meta class of %s', EventDetails::class, Post::class));
        Post::get(11)->meta(EventDetails::class);
    }

    public function testPostClassesNeedABootedInstance(): void
    {
        PostRegistry::reset();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('%s is no post class of a booted theme or plugin', Event::class));
        Event::find(1);
    }

    public function testDeleteMovesPostsOfEveryTypeToTheTrash(): void
    {
        Posts::add(10, 'fixture_event', ['post_status' => 'publish']);
        Posts::add(11, 'post', ['post_status' => 'publish']);

        // wp_delete_post() would delete the event permanently: it moves only posts and pages to the trash.
        Event::get(10)->delete();
        Post::get(11)->delete();
        self::assertSame([PostStatus::Trash, PostStatus::Trash], [Event::get(10)->getStatus(), Post::get(11)->getStatus()]);

        // A post already in the trash is deleted permanently, like in WordPress.
        Event::get(10)->delete();
        self::assertNull(Event::find(10));
    }

    public function testDeleteWithForceDeletesPermanently(): void
    {
        Posts::add(10, 'fixture_event', ['post_status' => 'publish']);

        Event::get(10)->delete(true);
        self::assertNull(Event::find(10));
    }

    public function testDeleteOfAMissingPostIsAnError(): void
    {
        Posts::add(10, 'fixture_event', ['post_status' => 'publish']);
        $event = Event::get(10);
        // Deleted by another request since loading.
        wp_delete_post(10, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Post 10 could not be trashed or deleted.');
        $event->delete();
    }

    public function testParentsOfHierarchicalTypesOnly(): void
    {
        Posts::add(30, 'page', ['post_status' => 'publish']);
        Posts::add(31, 'page', ['post_parent' => 30]);
        Posts::add(10, 'fixture_event');

        self::assertSame(30, Page::get(31)->getParent()?->getId());
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('%s::getParent(): the post type is not hierarchical.', Event::class));
        Event::get(10)->getParent();
    }

    public function testTemplatesGetPublishedPostsWithMetaByName(): void
    {
        Posts::add(10, 'fixture_event', ['post_status' => 'publish']);
        Posts::add(12, 'fixture_event', ['post_status' => 'draft']);
        Posts::add(13, 'attachment', ['post_status' => 'publish']);
        Posts::add(11, 'post', ['post_status' => 'publish']);
        Posts::addMeta(10, ['fixture_capacity' => '3']);
        Posts::addMeta(11, ['fixture_subtitle' => 'Sub']);
        $context = new RenderContext();
        [$store] = Posts::store($context);
        $functions = new PostTemplateFunctions($store, $context);

        $event = $functions->post(10);
        self::assertInstanceOf(Event::class, $event);
        $details = $event->meta('details');
        self::assertInstanceOf(EventDetails::class, $details);
        self::assertSame(3, $details->getCapacity());
        $extras = $functions->post(11)?->meta('extras');
        self::assertInstanceOf(PostTypeValid\Extras::class, $extras);
        self::assertSame('Sub', $extras->subtitle);
        self::assertNull($functions->post(12));
        self::assertNull($functions->post(13));
        self::assertNull($functions->post(null));
    }

    public function testValueFactoryLeavesOutReferencesOfOtherTypes(): void
    {
        [, $manifest] = Posts::store();
        Posts::add(10, 'fixture_event', ['post_status' => 'publish']);
        Posts::add(11, 'post', ['post_status' => 'publish']);
        $values = new ValueFactory($manifest['values']);

        $attributes = $values->createAttributes(PostTypeValid\EventTeaserAttributes::class, ['event' => 10], ['event' => ['type' => ['integer', 'null']]]);
        self::assertSame(10, $attributes->event?->getId());
        self::assertNull($values->createAttributes(PostTypeValid\EventTeaserAttributes::class, ['event' => 11], ['event' => ['type' => ['integer', 'null']]])->event);
    }
}
