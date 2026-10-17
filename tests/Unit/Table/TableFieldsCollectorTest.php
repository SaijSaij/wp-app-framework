<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Table;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Manifest\BuildException;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;

/**
 * Classes with #[TableFields] in the build: every method that returns a field is checked against its column.
 */
final class TableFieldsCollectorTest extends TestCase
{
    public function testEveryMethodThatReturnsAFieldIsChecked(): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('TableFieldsBroken'), ManifestBuilder::frameworkSource()], slug: 'my-theme')->build();
            self::fail('The build must fail.');
        } catch (BuildException $exception) {
            $ns = 'WebCodingCat\\WpAppFramework\\Tests\\Fixtures\\TableFieldsBroken\\';
            $fw = 'WebCodingCat\\WpAppFramework\\';
            self::assertSame([
                "{$ns}EntryFields::wrongClass(): {$ns}Entry::\$seats needs {$fw}Query\\Field\\IntField, {$fw}Query\\Field\\StringField given.",
                "{$ns}EntryFields::notNullable(): {$ns}Entry::\$seats needs {$fw}Query\\Field\\IntField, {$fw}Query\\Field\\NullableIntField given.",
                "{$ns}EntryFields::nullable(): {$ns}Entry::\$parent needs {$fw}Query\\Field\\NullableIntField, {$fw}Query\\Field\\IntField given.",
                "{$ns}EntryFields::wrongEnum(): {$ns}Entry::\$kind needs {$fw}Query\\Field\\EnumField<{$ns}Kind>, {$fw}Query\\Field\\EnumField<{$ns}Other> given.",
                "{$ns}EntryFields::unknown() returns the field \$sits, which {$ns}Entry does not have (fields: id, seats, parent, kind, title).",
                "{$ns}EntryFields::otherRow() returns a field of {$ns}Note; the class provides the fields of {$ns}Entry (#[TableFields]).",
                "{$ns}EntryFields::withParameter() returns a field and must have no parameters.",
                "{$ns}EntryFields::failing() failed: Not today.",
                "#[TableFields] on {$ns}EntryMoreFields names {$ns}Entry, whose fields {$ns}EntryFields already provides; one class per row class.",
                "#[TableFields] on {$ns}NoRowFields names {$ns}Kind, which is no row class (#[Table]) of this theme or plugin.",
            ], $exception->getErrors());
        }
    }
}
