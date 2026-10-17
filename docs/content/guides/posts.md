# Posts

Many sites need content of their own kind besides posts and pages: events, team members, venues, products. WordPress calls these *post types*, and their extra values *post meta*. Registering them by hand means a long list of arguments for `register_post_type()`, a meta key per value with a schema for the REST API, a form in the editor and code that reads the stored strings back into typed values.

The framework lets you declare all of this with classes, as you do with blocks and settings:

- a **post class** declares a post type and is at the same time the object of a post of that type: you load, change and save posts through it;
- a **meta class** declares typed fields that belong to posts of one or more post types;
- a **taxonomy class** declares a taxonomy for post types.

The build checks the declarations, and the framework registers everything on `init`, adds the forms to the editor and keeps the URLs working.

## A post type

A post class extends `AbstractPost` and carries `#[PostType]`:

```php
use WebCodingCat\WpAppFramework\Post\AbstractPost;
use WebCodingCat\WpAppFramework\Post\PostType;
use WebCodingCat\WpAppFramework\Post\PostTypeSupport;

#[PostType('event', label: 'Events', singularLabel: 'Event', supports: [PostTypeSupport::Title, PostTypeSupport::Editor], archive: true)]
final class Event extends AbstractPost {}
```

The framework registers the post type under the [namespace](../concepts.md#consumers-and-instances) of your theme or plugin: with the namespace `sections`, `event` becomes `sections_event`. That is the name WordPress stores with every post, so it must not change later. The post type is always shown in the REST API, which the block editor needs.

WordPress would label the new type with the texts of posts ("Add New Post"). The framework derives all labels from `label` and `singularLabel` instead: "Add New Event", "Edit Event", "No events found." and so on. The labels are English; when you need other texts, set them in `labels`:

```php
#[PostType('event', label: 'Veranstaltungen', singularLabel: 'Veranstaltung', labels: new PostTypeLabels(addNewItem: 'Neue Veranstaltung', editItem: 'Veranstaltung bearbeiten'))]
```

The URL of an event uses the name without namespace, with `-` instead of `_`: `/event/summer-concert/`. With `archive: true` the type also has an archive at `/event/`. `slug` sets another one. Block themes show posts of the type with the templates `single-sections_event.html` and `archive-sections_event.html`, which fall back to `single.html` and `archive.html`.

### Data types

Not every post type is a page of its own. A venue that events refer to is data: it needs no URL and no block content.

```php
#[PostType('venue', label: 'Venues', singularLabel: 'Venue', public: false, supports: [PostTypeSupport::Title])]
final class Venue extends AbstractPost {}
```

A post type of the framework is public unless you say otherwise; WordPress' own default for `register_post_type()` is the opposite. `public: false` hides it from the front end and searches; `showUi` (true by default) keeps it in the admin menu. Without `PostTypeSupport::Editor`, WordPress edits it on the classic edit screen instead of the block editor. The framework shows the meta of the type there as a form (see [Editing](#editing)).

## Meta fields

Meta fields are declared in a class of their own, a *meta class*. Its promoted constructor parameters are the fields, usually private with getters and setters of your own:

```php
use WebCodingCat\WpAppFramework\Value\Label;
use WebCodingCat\WpAppFramework\Value\RichText;
use WebCodingCat\WpAppFramework\Post\PostMeta;
use WebCodingCat\WpAppFramework\Value\ListOf;

#[PostMeta('details', title: 'Event details', postTypes: [Event::class])]
final class EventDetails
{
    /**
     * @param list<Ticket> $tickets
     */
    public function __construct(
        #[Label('Start')]
        private string $start = '',
        private int $capacity = 0,
        private ?Venue $venue = null,
        #[ListOf(Ticket::class)]
        private array $tickets = [],
        #[RichText(allowedFormats: ['core/bold', 'core/link'])]
        private string $note = '',
    ) {}

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): void
    {
        $this->capacity = $capacity;
    }

    // …
}
```

Each field is stored in its own meta key: the namespace, `_` and the field name in snake case, here `sections_start`, `sections_capacity`, `sections_venue`. The framework registers each key with `register_post_meta()`, with the schema of the field, so the REST API validates every value. Meta classes follow the rules of [values](./values.md) with the markers of [settings](./settings.md#types-of-settings): rich text is edited with the classic editor, images with the media library, and list items with [`#[Identifier]`](./values.md#identifiers) get missing, invalid and duplicate identifiers filled in when a value is saved.

A field typed as a post class – `?Venue` here – refers to a post: it is stored as the ID and you get the object of the post. A list of `#[ListOf(Venue::class)]` stores IDs as well.

A [`Link`](./values.md#links) – `private Link $registration = new Link()` – is stored as an object with the URL, the ID of the linked post and "Open in new tab", and you get it with the current URL of the post. `#[PlainText(multiline: true)]` keeps line breaks; print it with `|nl2br`.

One post type can have several meta classes, and one meta class can belong to several post types. Meta for the post types of WordPress names the framework classes `Post` and `Page`:

```php
use WebCodingCat\WpAppFramework\Post\Page;
use WebCodingCat\WpAppFramework\Post\Post;

#[PostMeta('extras', title: 'Extras', postTypes: [Post::class, Page::class])]
final class Extras
{
    public function __construct(
        #[PlainText]
        private string $subtitle = '',
    ) {}
}
```

## Taxonomies

A taxonomy class carries `#[Taxonomy]` and has no fields:

```php
#[Taxonomy('genre', label: 'Genres', singularLabel: 'Genre', postTypes: [Event::class, Post::class], hierarchical: true)]
final class Genre {}
```

It is registered as `sections_genre`, with labels derived like those of a post type. The block editor shows it in the document settings of its post types.

WordPress' own taxonomies have framework classes too: `Category` for `category` and `Tag` for `post_tag`. They register nothing; use them where a taxonomy class is expected, for example `$post->getTerms(Category::class)` or `Terms::in(Tag::class, [$id])` in a [query](./queries.md#posts-and-pages).

## Working with posts

The post class loads posts, and `meta()` returns the meta objects of a post:

```php
$event = Event::get($id);
$details = $event->meta(EventDetails::class);

$details->setCapacity(120);
$event->setTitle('Summer concert');
$event->save();
```

`get()` throws an exception if there is no event with the ID; `find()` returns null instead. `meta()` loads a meta object on first use and returns the same object afterwards, so changes stay until `save()`.

`save()` checks every loaded meta object against its schema first. If a value is invalid, it throws a `PostValidationException` and writes nothing. Otherwise it saves the post and writes the meta values that changed since they were loaded; values that another request changed in the meantime stay as they are. Then WordPress' action `wp_after_insert_post` fires, as after saving in the editor or through the REST API: its callbacks see the post together with its meta and terms – also when only meta values changed. A post created with `new` is inserted as a draft:

```php
$event = new Event();
$event->setTitle('Autumn concert');
$event->meta(EventDetails::class)->setVenue(Venue::get($venueId));
$event->setTerms(Genre::class, [$jazzId]);
$event->save();
```

`delete()` moves the post to the trash, whatever its post type, so an editor can restore it. `delete(true)` deletes it permanently, together with its meta values and term assignments:

```php
Event::get($id)->delete();
```

WordPress' own `wp_delete_post()` moves only posts and pages to the trash and deletes posts of other types permanently; the framework uses `wp_trash_post()` instead. A post already in the trash is deleted permanently, as in WordPress. While the trash is disabled (the constant `EMPTY_TRASH_DAYS` is 0), every call deletes permanently. If WordPress cannot trash or delete the post, for example because it no longer exists, `delete()` throws a `RuntimeException`.

The base class provides the data of the post: `getId()`, title, content, excerpt, status (`PostStatus`), slug, dates, author, parent (hierarchical types), `getUrl()`, terms and `toWpPost()`. Your post class may add methods of its own, for example `getCapacity()` that reads the meta. It keeps no data of its own: a property in a post class is a build error, because it would not be stored.

Post, meta and taxonomy classes are data, not [services](./services.md): they cannot be injected. `get()` and `find()` work once the theme or plugin has booted.

To load a list of posts – filtered by meta fields or terms, sorted, in pages – start a query with `Event::query()`; [Queries](./queries.md) explains it.

### Invalid stored values

Other code can store anything in a meta key. When a meta object is loaded, each stored value is checked against its schema; an invalid value is logged (`wp-app-framework: post meta: …`), and the field keeps the default of its constructor parameter. A reference to a post that no longer exists, has another type or is not published becomes null or is left out of its list.

## Editing

In the block editor every meta class of the edited post type is a panel in the document settings, titled with the `title` of `#[PostMeta]`. The editor saves the fields together with the post, in the same request, and shows the error of an invalid value as a notice. Links are edited with the link control of the block editor.

On the classic edit screen – post types without the block editor – every meta class is a meta box with the same form; links have the form of the settings page, which searches posts only. Invalid values are not saved and listed in a notice after saving.

The framework removes WordPress' "Custom Fields" box from post types with meta: it would edit the meta keys as plain strings, past the schemas. Taxonomies are edited with the controls of WordPress.

## Templates

`post(id)` returns the post object in Twig, for a post type of your theme or plugin, `post` or `page`. Getters are attributes, and meta classes are named by their `#[PostMeta]` name:

```twig
{% set event = post(postId) %}
{% if event %}
    <h2>{{ event.title }}</h2>
    <p>{{ event.meta('details').capacity }} seats at {{ event.meta('details').venue.title }}</p>
{% endif %}
```

Outside renders for the block editor, `post()` returns null for posts that are not published. It does not ask whether the post type is public: a post class stands for a type of your own theme or plugin, and data types such as venues have no pages of their own but are still shown in other templates. References to post classes follow the same rule (see [Values](./values.md)). The WordPress functions `wp_post_url()`, `wp_post_title()` and the others are stricter: they return values only for posts that are publicly viewable, by their status and their type (see [Twig](./twig.md#wordpress-functions)). Like everything in Twig, the values are escaped; print rich text with `|raw`.

Scalar meta fields also work with the post meta source of block bindings in templates, because their keys are shown in the REST API and not protected. A paragraph bound to `sections_start` shows the start of the event.

## Rewrite rules

WordPress builds the URLs of post types and taxonomies only when its rewrite rules are flushed; usually you would visit the permalink settings after adding a post type. The framework does it for you: it keeps a hash of the declarations that affect URLs and flushes the rules when it changes. It also flushes after the theme was switched to and after the plugin was activated (see [Activation, deactivation and uninstallation](./lifecycle.md)), since another theme or plugin may have flushed the rules without your post types in between. When a plugin is deleted, the framework deletes the posts of its post types, the terms of its taxonomies and its meta keys. The check costs no database query: the hash is stored in an autoloaded option, and a theme or plugin without post types and taxonomies only looks whether such an option is left over from earlier declarations. After a theme switch or deactivation, the old rules stay until the next flush; their URLs answer with "not found".

## Rules

[`#[PostType(name, label, singularLabel, labels: null, description: '', public: true, showUi: true, supports: [Title, Editor], hierarchical: false, archive: false, slug: null, menuIcon: null, menuPosition: null)]`](../api/php/Post/PostType.md) on a subclass of `AbstractPost`:

- `name`: lowercase letters, digits and `_`, starting with a letter; the full name `{namespace}_{name}` has at most 20 characters (the limit of WordPress).
- `label` and `singularLabel` must not be empty; `slug` consists of lowercase letters, digits, `-` and `_`.
- The class is not abstract and declares no properties.
- `custom-fields` is added to `supports` when the type has meta; without it the REST API has no meta.

[`#[PostMeta(name, title, postTypes)]`](../api/php/Post/PostMeta.md) on a meta class:

- The class is not readonly and not abstract; all its fields are promoted constructor parameters (any visibility), each with a default.
- `name`: lowercase letters, digits and `_`, starting with a letter, unique per post type.
- `postTypes`: a non-empty list of post classes (`#[PostType]`, `Post`, `Page`).
- Meta keys `{namespace}_{field_in_snake_case}` are unique per post type.
- A field on the top level may be nullable only if it holds an ID – a reference or an image: the REST API of WordPress leaves out meta fields whose type allows null. A missing ID is stored as no value (0 in the REST API).
- `#[Content]` and `#[RichText(postContent: true)]` are build errors; `allowedFormats` may only contain the formats of the classic editor.

[`#[Taxonomy(name, label, singularLabel, postTypes, labels: null, description: '', public: true, showUi: true, hierarchical: false, slug: null)]`](../api/php/Post/Taxonomy.md) on a class without constructor parameters: the full name has at most 32 characters.

A class has at most one of `#[PostType]`, `#[PostMeta]` and `#[Taxonomy]`. Post, meta and taxonomy classes cannot be injected and are no parameters of hooks, REST routes or template functions.
