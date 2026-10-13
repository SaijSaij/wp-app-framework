# Queries

A page often shows a list of posts: the next five events, the events of a genre, a page of news. In plain WordPress you describe such a list as an array of arguments for `WP_Query`, with the names of post types, meta keys and taxonomies as strings. A typo finds nothing, and your editor cannot help you.

The framework lets you write the same query with the classes you already have. You start at the [post class](./posts.md), add conditions on meta fields and terms, sort, and get post objects back:

```php
$events = Event::query()
    ->where(EventDetailsFields::capacity()->greaterThan(10))
    ->where(Terms::in(Genre::class, [$jazz]))
    ->orderBy(EventDetailsFields::start())
    ->limit(5)
    ->get();
```

Every name in this query is a class or a method, so your editor completes it and PHPStan checks it. The framework turns the query into arguments of `WP_Query` and runs it there. Plugins that filter queries, for example translation plugins, keep working.

## How it works

`Event::query()` returns a `PostQuery` for posts of the type. A query never changes: every method returns a changed copy. You can therefore keep a query, pass it to other code and vary it without side effects:

```php
$upcoming = Event::query()->where(EventDetailsFields::start()->greaterOrEqual($today));

$teaser = $upcoming->limit(3)->get();
$total = $upcoming->count();   // counts all upcoming events, not 3
```

The flip side is that a call whose result you do not keep does nothing. To make that mistake visible, every method that returns a query carries PHP's `#[\NoDiscard]`. PHP warns when you drop the result, and PHPStan reports the line:

```php
$query->limit(3);            // warning: the result is discarded
$query = $query->limit(3);   // right
```

Without further methods, a query finds all published posts of the type, sorted the way WordPress sorts them (newest first). The methods `get()`, `first()`, `count()` and `paginate()` run the query.

## Meta fields in queries

To filter or sort by a meta field, the query needs the field as an object. A field object knows its meta class, its name and its type. The type decides which comparisons it offers: an `IntField` takes integers, an `EnumField<EventKind>` takes the cases of `EventKind`.

The field objects come from a class of their own next to the meta class, marked with `#[MetaFields]`. This keeps the meta class as it is, and you decide for each meta class whether its fields can be queried at all.

### Generating the class

The quickest way is to let the framework write it. Run this in your theme or plugin:

```sh
vendor/bin/wp-app-framework meta-fields 'Sections\Post\EventDetails'
```

The command writes `EventDetailsFields.php` into the directory of `EventDetails`, with one method per field that can be queried:

```php
#[MetaFields(EventDetails::class)]
final class EventDetailsFields
{
    public static function capacity(): IntField
    {
        return new IntField(EventDetails::class, 'capacity');
    }

    /**
     * @return EnumField<EventKind>
     */
    public static function kind(): EnumField
    {
        return new EnumField(EventDetails::class, 'kind', EventKind::class);
    }

    /**
     * @return PostField<Venue>
     */
    public static function venue(): PostField
    {
        return new PostField(EventDetails::class, 'venue', Venue::class);
    }
}
```

The file is ordinary source code of your project. Commit it, remove methods you do not need, or add a method later. The command does not replace an existing file unless you pass `--force`. It is the only command of the framework that writes source code, and it does so only when you run it.

### Writing it by hand

You can also write the class yourself, for example with just the one field you query. The build checks it either way: it calls every public static method that returns a field and reports a field of another meta class, a field name that does not exist, a field class that does not fit the type, and a field that cannot be queried.

Fields that hold a list or a value class cannot be queried. WordPress stores them serialized in one meta value, and SQL cannot look inside.

### Missing values

A post that was never saved with a field has no meta value for it. When you load the meta object, such a field has the default of its constructor parameter. Queries follow the same rule. If the default meets a condition, the condition also finds posts without a stored value: `capacity()->lessThan(5)` finds events without a stored capacity, because the default is 0.

Sorting follows it too: a post without a value sorts like the default. WordPress' own sorting by meta values cannot do this reliably, so the framework adds the sort order to the SQL of the query itself. It uses a filter of WordPress that it adds only while the query runs.

## Conditions

`where()` takes conditions; all of them must be met. Conditions come from field objects, from `Terms` for taxonomies, and from the groups `Any` and `All`:

```php
$events = Event::query()
    ->where(
        Any::of(
            EventDetailsFields::kind()->equals(EventKind::Jazz),
            EventDetailsFields::venue()->equals($venue),
        ),
        Terms::notIn(Genre::class, [$children]),
    )
    ->get();
```

`Any::of()` is met if one of its conditions is met, `All::of()` if all of them are; groups can contain groups. WordPress keeps conditions on meta fields and on terms in two separate parts of a query and always joins the two with AND. A group therefore contains either meta conditions or term conditions; mixing them throws an exception.

The data of the post has methods of its own: `status()`, `ids()`, `exclude()`, `author()`, `parent()`, `search()`, `publishedAfter()` and `publishedBefore()`.

## Sorting and pages

`orderBy()` sorts by post data (`PostOrder::Title`, `PostOrder::Date`, …) or a field, ascending unless you pass `Direction::Desc`. Each further call sorts the posts that are still equal.

`limit()` and `offset()` restrict `get()`. For pages, use `paginate()`, which also counts all matching posts:

```php
$page = Event::query()->orderBy(PostOrder::Date, Direction::Desc)->paginate(perPage: 10, page: $number);

foreach ($page->getPosts() as $event) { /* … */ }
$pages = $page->getPageCount();
```

## Caching

`cache()` stores the result in the [cache](./cache.md) of your theme or plugin. The framework stores the IDs of the posts and the number of matching posts, and loads the post objects from them on each call:

```php
$events = Event::query()->where(Terms::in(Genre::class, [$jazz]))->cache(ttl: 600)->get();
```

You do not need to name tags for the posts. The framework adds the tag of the post type and of every taxonomy in the conditions, so a changed event, meta value or term assignment invalidates the entry. Further tags and variants work as with `remember()`. A random order cannot be cached.

WordPress caches the IDs of queries in the object cache as well, but only with a persistent object cache, and every change of any post invalidates all of them. `cache()` also works with the file backend and is invalidated per post type.

## Using results in templates

Queries belong in PHP, not in templates. A block runs its query in `render()` and passes the post objects to its template, where their getters work as with [`post()`](./posts.md#templates):

```php
public function render(EventListAttributes $attrs): string
{
    return $this->renderer->render('Block/EventList/EventList.twig', [
        'events' => Event::query()->limit($attrs->limit)->get(),
    ]);
}
```

```twig
{% for event in events %}
    <a href="{{ event.url }}">{{ event.title }}</a>
{% endfor %}
```

## Rules

`AbstractPost::query()` returns a `PostQuery` of the post class; it works once the theme or plugin has booted. Every method that returns a query is marked `#[\NoDiscard]`.

| Method | Effect |
|---|---|
| `where(Condition ...$conditions)` | All conditions must be met; adds to earlier calls. |
| `status(PostStatus ...)` | These statuses instead of `Publish`. The capabilities of the current user are not checked. |
| `ids(AbstractPost\|int ...)` | Only these posts; without arguments no posts at all. |
| `exclude(AbstractPost\|int ...)` | Not these posts; adds to earlier calls. |
| `author(int ...)` | Posts of these authors. |
| `parent(?AbstractPost)` | Children of the post, or top-level posts with null; hierarchical post types only. |
| `search(string)` | The search of WordPress; the text must not be empty. |
| `publishedAfter(DateTimeInterface)`, `publishedBefore(…)` | Publication date, the time itself excluded. |
| `orderBy(PostOrder\|Field, Direction = Asc)` | Sorts; further calls sort what is still equal. Without it: newest first. |
| `limit(int)`, `offset(int)` | At most this many posts (at least 1), skipping some (with `limit()`). Without `limit()`, all posts. |
| `cache(int $ttl, array $tags = [], array $vary = [])` | Caches the result; tags of the post type and the taxonomies are added. |
| `get()` | The posts, as objects of the post class. |
| `first()` | The first post or null. |
| `count()` | The number of all matching posts, regardless of `limit()` and `offset()`. |
| `paginate(int $perPage, int $page)` | A `PostPage` with `getPosts()`, `getTotal()`, `getPage()`, `getPerPage()` and `getPageCount()`; not with `limit()` or `offset()`. |

Fields and their conditions:

| Field of the meta class | Field class | Conditions |
|---|---|---|
| `string` (also `#[PlainText]`, `#[RichText]`, `#[Url]`) | `StringField` | `equals`, `notEquals`, `in`, `notIn`, `greaterThan`, `greaterOrEqual`, `lessThan`, `lessOrEqual`, `between`, `contains` |
| `int` | `IntField` | as `StringField`, without `contains` |
| `float` | `FloatField` | as `IntField` |
| `bool` | `BoolField` | `equals` |
| backed enum | `EnumField<E>` | `equals`, `notEquals`, `in`, `notIn` |
| post class (`?Venue`) | `PostField<P>` | `equals`, `in`, `notIn`, `isNull`, `isNotNull` |
| `#[Image]`, `#[PostReference]`, `#[TermReference]`, `#[UserReference]` on `?int` | `IdField` | `equals`, `in`, `notIn`, `isNull`, `isNotNull` |

- Strings are compared without regard to case, like the default collations of the database. `contains()` matches `%` and `_` literally.
- A meta value that is not stored counts as the default in conditions and in the sort order.
- `Terms::in()`, `notIn()` and `all()` take the taxonomy class, a non-empty list of `WP_Term` objects or term IDs and `includeChildren` (default true); `Terms::exists()` and `notExists()` take the taxonomy class.
- `Any::of()` and `All::of()` need at least one condition, all on meta fields or all on terms.

`#[MetaFields(metaClass)]` on a class of your own:

- `metaClass` is a meta class (`#[PostMeta]`) of the theme or plugin; one class per meta class.
- Every public static method that returns a field has no parameters and returns a field of `metaClass` with an existing field name and the field class of its type (with the enum or post class of the field). Other methods are ignored.
- The class is no service. In development mode the build runs again when signatures change, not when only a method body changes; a query checks its fields again when it runs.

`wp-app-framework meta-fields <meta class> [--force]` writes `{MetaClass}Fields.php` next to the meta class. An existing file is replaced only with `--force`.
