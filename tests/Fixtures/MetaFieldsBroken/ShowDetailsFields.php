<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\MetaFieldsBroken;

use RuntimeException;
use WebCodingCat\WpAppFramework\Post\Post;
use WebCodingCat\WpAppFramework\Query\Field\EnumField;
use WebCodingCat\WpAppFramework\Query\Field\Field;
use WebCodingCat\WpAppFramework\Query\Field\IntField;
use WebCodingCat\WpAppFramework\Query\Field\PostField;
use WebCodingCat\WpAppFramework\Query\Field\StringField;
use WebCodingCat\WpAppFramework\Query\MetaFields;
use WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeValid\Density;

/**
 * One method per error; the valid ones are not reported.
 */
#[MetaFields(ShowDetails::class)]
final class ShowDetailsFields
{
    public static function seats(): IntField
    {
        return new IntField(ShowDetails::class, 'seats');
    }

    public static function wrongClass(): StringField
    {
        return new StringField(ShowDetails::class, 'seats');
    }

    /**
     * @return EnumField<Density>
     */
    public static function wrongEnum(): EnumField
    {
        return new EnumField(ShowDetails::class, 'kind', Density::class);
    }

    /**
     * @return PostField<Post>
     */
    public static function wrongPost(): PostField
    {
        return new PostField(ShowDetails::class, 'next', Post::class);
    }

    public static function unknown(): IntField
    {
        return new IntField(ShowDetails::class, 'sits');
    }

    public static function list(): StringField
    {
        return new StringField(ShowDetails::class, 'tags');
    }

    public static function otherMeta(): StringField
    {
        return new StringField(ShowExtras::class, 'subtitle');
    }

    public static function withParameter(string $name): Field
    {
        return new IntField(ShowDetails::class, $name);
    }

    public static function failing(): Field
    {
        throw new RuntimeException('Not today.');
    }

    /**
     * Not a field: ignored.
     */
    public static function label(): string
    {
        return 'Show';
    }
}
