<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RestValid;

use RuntimeException;
use Saij\WpAppFramework\Rest\Format;
use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\Param;
use Saij\WpAppFramework\Rest\Permission\Callback;
use Saij\WpAppFramework\Rest\Permission\Capability;
use Saij\WpAppFramework\Rest\Permission\PublicAccess;
use Saij\WpAppFramework\Rest\RestController;
use Saij\WpAppFramework\Rest\RestException;
use Saij\WpAppFramework\Rest\RestRoute;
use WP_REST_Request;
use WP_REST_Response;

#[RestController(Api::NAMESPACE, base: '/items')]
final class ItemController
{
    /**
     * @return array<string, mixed>
     */
    #[RestRoute('', HttpMethod::Get, new PublicAccess())]
    public function index(
        #[Param(description: 'Page size', minimum: 1, maximum: 50)]
        int $perPage = 10,
        ?Variant $variant = null,
        bool $flag = false,
        float $ratio = 0.5,
    ): array {
        return ['perPage' => $perPage, 'variant' => $variant, 'flag' => $flag, 'ratio' => $ratio];
    }

    /**
     * @return array<string, mixed>
     */
    #[RestRoute('/{id}', HttpMethod::Get, new PublicAccess())]
    public function show(int $id, ItemRepository $items, Variant $variant = Variant::Short): array
    {
        return ['id' => $id, 'title' => $items->getTitle($id), 'variant' => $variant];
    }

    #[RestRoute('/{id}', [HttpMethod::Put, HttpMethod::Patch], new Callback(self::canEdit(...)))]
    public function update(
        int $id,
        #[Param(maxLength: 20)]
        string $title,
        WP_REST_Request $request,
        #[Param(format: Format::Email)]
        ?string $contact = null,
    ): WP_REST_Response {
        return new WP_REST_Response(['id' => $id, 'title' => $title, 'contact' => $contact, 'request' => $request::class], 201);
    }

    #[RestRoute('/{id}', HttpMethod::Delete, new Capability('delete_posts'))]
    public function delete(int $id, ItemRepository $items): void
    {
        $items->delete($id);
    }

    #[RestRoute('/level/{level}', HttpMethod::Get, new PublicAccess())]
    public function level(Level $level): int
    {
        return $level->value;
    }

    /**
     * @return array<string, mixed>
     */
    #[RestRoute('/missing/{slug}', HttpMethod::Get, new PublicAccess())]
    public function missing(string $slug): array
    {
        throw new RestException('fixture_not_found', sprintf('No item "%s".', $slug), 404, ['slug' => $slug]);
    }

    /**
     * @return array<string, mixed>
     */
    #[RestRoute('/fail', HttpMethod::Post, new PublicAccess())]
    public function fail(): array
    {
        throw new RuntimeException('fixture failure');
    }

    public static function canEdit(int $id, ItemRepository $items): bool
    {
        return $items->isEditable($id);
    }
}
