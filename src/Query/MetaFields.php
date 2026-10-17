<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Query;

use Attribute;

/**
 * Marks a class whose public static methods return the fields of a meta class for queries:
 *
 *     #[MetaFields(EventDetails::class)]
 *     final class EventDetailsFields
 *     {
 *         public static function capacity(): IntField
 *         {
 *             return new IntField(EventDetails::class, 'capacity');
 *         }
 *     }
 *
 * Write it by hand or generate it with `wp-app-framework meta-fields <meta class>`. The build calls every public
 * static method that returns a field and checks the field against the meta class. The class is no service.
 *
 * @api Attributes/Posts
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class MetaFields
{
    /**
     * @param class-string $metaClass The meta class (#[PostMeta]).
     */
    public function __construct(
        public string $metaClass,
    ) {}
}
