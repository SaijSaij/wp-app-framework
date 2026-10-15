# Cache

Some work is too expensive to repeat on every request: a block that queries the latest posts, a REST route that adds up statistics, a service that calls an external API. The framework can keep the result and use it again until something changes. You decide what is cached and what a result depends on; the framework stores it, finds it again and throws it away when it is outdated.

This page explains how the cache works, how you cache a block, a REST route and data of your own, and how entries are invalidated.

## How it works

Every theme or plugin has a cache of its own, named after its [slug](../concepts.md#consumers-and-instances). An entry is stored under a key. The framework builds the key from four things:

- **The inputs.** For a block, its attributes, its inner content and the values of the context it uses (`usesContext`, such as `postId`). For a REST route, the arguments of the request. For your own data, the key you choose.
- **The variants.** The circumstances you declare the result depends on, such as the logged-in user or a parameter of the URL. Each variant adds its current value to the key, so every value gets an entry of its own.
- **The tags.** Each tag has a version; invalidating a tag gives it a new version, so the entries with that tag are never read again.
- **The code.** New code never reads entries of old code. In release mode the framework uses the version of your PHP code and templates from the release build. In development mode it looks at the modification times of your files, so a changed template or class takes effect on the next request.

An entry also has a lifetime, the *ttl* in seconds. It expires after that time, even if no tag is invalidated.

Nothing depends on the request unless you say so. If a block shows something different for logged-in users and you declare no variant for it, the first visitor decides what everybody sees until the entry expires. Declare every circumstance the result depends on.

## A block, step by step

A block that shows the latest posts queries the database each time it renders. With `#[Cache]` it renders once and is then read from the cache:

```php
// src/Block/LatestPosts/LatestPosts.php
use Saij\WpAppFramework\Cache\Cache;
use Saij\WpAppFramework\Cache\Tag\PostTypeTag;

#[Block('latest-posts', title: 'Latest posts', category: BlockCategory::Widgets)]
#[Cache(ttl: 3600, tags: [new PostTypeTag('post')])]
final class LatestPosts
{
    public function __construct(private readonly TemplateRenderer $templates) {}

    public function render(LatestPostsAttributes $attrs): string
    {
        $posts = get_posts(['numberposts' => $attrs->count]);

        return $this->templates->render('Block/LatestPosts/LatestPosts.twig', ['attrs' => $attrs, 'posts' => $posts]);
    }
}
```

The block now renders once per combination of attributes and stays cached for an hour. The `PostTypeTag` invalidates it as soon as a post is published, changed, trashed or deleted, so a new post shows up right away.

If the block shows a page of the list depending on a URL parameter, declare that parameter as a variant:

```php
#[Cache(ttl: 3600, tags: [new PostTypeTag('post')], vary: [new QueryParamVary('page')])]
```

Now `?page=2` gets an entry of its own.

The cache is used only on the front end. Renders for the block editor and its preview always run `render()`, so editors see their changes at once. Excerpts are never cached either.

### What a cached block keeps

A block read from the cache skips its template and `render()`. Everything that ends up in the HTML is kept. Beyond that, the framework keeps one more thing: the scripts, styles and script modules `render()` enqueues are recorded with the entry and enqueued again when the block comes from the cache. The block's [view scripts](./frontend.md) are enqueued as usual. Register the scripts and styles you enqueue before blocks render, for example on `init`: when the block comes from the cache, a handle that is not registered is an error.

Other side effects of `render()` do not happen when the block comes from the cache. Decisions WordPress makes per request are kept as they were, too: WordPress decides `loading="lazy"` and `fetchpriority` of images by their order on the page, so a cached block keeps the attributes of the render that stored it. A block with inner blocks caches only its own output; the inner blocks render before it, as always, and their output is part of its input.

### Template functions that depend on the request

Some [WordPress functions in Twig](./twig.md#wordpress-functions) depend on the request. In a cached block, the framework checks them:

- `wp_is_user_logged_in()` needs the variant `LoggedInVary`, `RoleVary` or `UserVary`.
- `wp_logout_url()` contains a nonce of the user's session and cannot be used in cached blocks.
- `wp_date()` without a timestamp and `wp_human_time_diff()` without a second timestamp depend on the current time and cannot be used in cached blocks.

A call that breaks these rules throws an error that names the block and the missing variant. Your own PHP code is not checked: if `render()` asks for the current user, declare `UserVary` yourself.

## A REST route

A route that only reads data can be cached the same way. Put `#[Cache]` on the method:

```php
#[RestRoute('/stats', HttpMethod::Get, new Capability('edit_posts'))]
#[Cache(ttl: 300, tags: [new TaxonomyTag('category')], vary: [new UserVary()])]
public function stats(int $limit = 10): array
{
    // …
}
```

The arguments of the request are part of the key: `?limit=5` and `?limit=20` get entries of their own. The permission check runs on every request, also when the response comes from the cache. Errors – a `RestException` or any other exception – are answered as usual and never stored.

A route may return a `WP_REST_Response` as well. The cache keeps its data, status and headers, but only for a status below 400: a deliberate 404 is answered every time, like an error.

A route may also take the `WP_REST_Request`. Only the arguments of the method are part of the key, though: whatever the route reads from the request beyond them – a header, a parameter it does not declare – must be declared as a variant, such as `new HeaderVary('Accept-Language')`.

A cached route answers only `GET`.

## Data of your own

For anything else, inject `CacheStore` and call `remember()`. It returns the cached value or calls your function, stores its result and returns it:

```php
use Saij\WpAppFramework\Cache\CacheStore;

final readonly class Weather
{
    public function __construct(private CacheStore $cache, private WeatherClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function current(string $city): array
    {
        return $this->cache->remember(
            'weather-' . $city,
            fn(): array => $this->client->fetch($city),
            ttl: 600,
            tags: [Feed::Weather],
        );
    }
}
```

The key identifies the entry within your theme or plugin; include everything the value depends on, or declare it as a variant. Values must be plain data: `null`, booleans, numbers, strings and arrays of them. If your function throws, nothing is stored and the exception reaches you.

Queries of posts have a shortcut: `Event::query()->…->cache(ttl: 600)` caches their result with the tags of the post type and of the taxonomies they use (see [Queries](./queries.md#caching)).

Rows of [tables](./tables.md) have no tag of the framework. To cache a value computed from a table, give it a tag of your own and invalidate that tag where your code saves or deletes rows, right after `save()`.

## Invalidating entries

Three tags are invalidated by the framework itself:

- `PostTypeTag('product')` when a post of that type is created, changed, trashed, deleted, changes its status or its post meta. The edit lock the block editor sets while somebody edits a post does not count. Name your own post types by their [post class](./posts.md): `new PostTypeTag(Event::class)`.
- `TaxonomyTag('category')` or `TaxonomyTag(Category::class)` when a term of that taxonomy is created, changed or deleted, changes its term meta, or is assigned to or removed from a post.
- `SettingsTag(SiteSettings::class)` when the [settings group](./settings.md) is saved.

For everything else, define tags of your own. A tag is a case of a string-backed enum that implements `CacheTag`:

```php
use Saij\WpAppFramework\Cache\CacheTag;

enum Feed: string implements CacheTag
{
    case Weather = 'weather';
}
```

Invalidate it with `CacheStore::invalidate()`, for example in a hook that runs when the external data changes:

```php
$this->cache->invalidate(Feed::Weather);
```

Invalidating a tag does not delete anything. It gives the tag a new version, so the entries with the old version are no longer found and expire after their ttl. The versions are stored as options of your theme or plugin.

## Where entries are stored

The framework stores entries in the first available backend of a list:

1. `object`: the persistent object cache, if the site has one (an `object-cache.php` drop-in such as Redis).
2. `file`: PHP files below `wp-content/cache/{slug}/data`, written atomically and read through OPcache. A daily [WP-Cron event](./cron.md) deletes expired files.
3. `transient`: transients in the database.

The site can change the list with the filter `wp_app_framework/cache/backends/{slug}`, for example in a must-use plugin. The values are plain strings, so the filter works without the theme's classes. An empty list turns the cache off:

```php
add_filter('wp_app_framework/cache/backends/my-theme', fn(): array => ['transient']);
add_filter('wp_app_framework/cache/backends/my-plugin', fn(): array => []);
```

`CacheStore::getBackendName()` tells you which backend is used in the current request.

## Variants

| Variant | Value in the key |
|---|---|
| `new UserVary()` | ID of the current user, `0` for visitors who are not logged in |
| `new LoggedInVary()` | Whether the visitor is logged in |
| `new RoleVary()` | The roles of the current user |
| `new UrlVary()` | Path of the requested URL, without the query string |
| `new QueryParamVary('page')` | A parameter of the query string; a missing parameter differs from an empty one |
| `new HeaderVary('Accept-Language')` | A request header; a missing header differs from an empty one |
| `new CookieVary('region')` | A cookie; a missing cookie differs from an empty one |
| `new ServiceVary(Region::class)` | The value of a service of yours implementing `VaryProvider` (`value(): string\|int\|float\|bool\|null`) |

Data sent with a `POST` request is no variant. A block that shows the result of a form submission must not be cached.

## Rules

[`#[Cache(ttl, tags: [], vary: [])]`](../api/php/Cache/Cache.md):

| Parameter | Type | Rule |
|---|---|---|
| `ttl` | `int` | Required; lifetime in seconds, at least 1. |
| `tags` | `list<CacheTag>` | `PostTypeTag` (a name of 1–20 characters or a post class), `TaxonomyTag` (a name of 1–32 characters or a taxonomy class), `SettingsTag` with a class that has `#[SettingsGroup]` in the same theme or plugin, or a case of a string-backed enum implementing `CacheTag`. No tag twice. |
| `vary` | `list<CacheVary>` | The variants above. `QueryParamVary`, `HeaderVary` and `CookieVary` need a name; the class of a `ServiceVary` must be a service implementing `VaryProvider`. No variant twice. |

Build errors:

- `#[Cache]` on a class without `#[Block]` or on a method without `#[RestRoute]`.
- A ttl below 1, an invalid tag or variant, a tag or variant listed twice.
- A cached route that answers another method than `GET`, or declares a return type other than `array`, `string`, `int`, `float`, `bool`, `null`, `WP_REST_Response` or a union of them (`void`, other objects and `mixed` are errors).

At run time:

- `CacheStore::remember(string $key, Closure $compute, int $ttl, array $tags = [], array $vary = [])` checks its arguments like the build; an empty key, an invalid ttl, tag or variant throws an `InvalidArgumentException`. A value that is not plain data throws a `LogicException`. A cached REST route whose data or headers are not plain data answers with status 500, like any other exception.
- `CacheStore::invalidate(CacheTag ...$tags)` invalidates the tags of this theme or plugin.
- The filter `wp_app_framework/cache/backends/{slug}` returns a list of `'object'`, `'file'` and `'transient'`; another value throws a `LogicException`.
- Blocks are never cached in renders for the block editor, its preview or excerpts. Errors and REST responses with a status of 400 or more are never cached.
