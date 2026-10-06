<?php

namespace HarryGulliford\Firebird\Tests;

use HarryGulliford\Firebird\Tests\Support\MigrateDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class SchemaTest extends TestCase
{
    use MigrateDatabase;

    #[Test]
    public function it_has_table()
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('foo'));
    }

    #[Test]
    public function it_lists_tables()
    {
        $tables = Schema::getTableListing();

        $this->assertCount(2, $tables);
        $this->assertContains('users', $tables);
        $this->assertContains('orders', $tables);
    }

    #[Test]
    public function it_gets_tables()
    {
        $tables = Schema::getTables();

        $this->assertIsArray($tables);
        $this->assertCount(2, $tables);

        foreach ($tables as $table) {
            $this->assertArrayHasKey('name', $table);
            $this->assertArrayHasKey('schema', $table);
            $this->assertArrayHasKey('size', $table);
            $this->assertArrayHasKey('comment', $table);
            $this->assertArrayHasKey('collation', $table);
            $this->assertArrayHasKey('engine', $table);

            $this->assertIsString($table['name']);
        }

        $this->assertContains('users', array_column($tables, 'name'));
        $this->assertContains('orders', array_column($tables, 'name'));
    }

    #[Test]
    public function it_has_column()
    {
        $this->assertTrue(Schema::hasColumn('users', 'id'));
        $this->assertFalse(Schema::hasColumn('users', 'foo'));
    }

    #[Test]
    public function it_has_columns()
    {
        $this->assertTrue(Schema::hasColumns('users', ['id', 'country']));
        $this->assertFalse(Schema::hasColumns('users', ['id', 'foo']));
    }

    #[Test]
    public function it_lists_columns()
    {
        $columns = Schema::getColumnListing('users');

        $this->assertCount(10, $columns);

        $expectedColumns = [
            'id', 'name', 'email', 'city', 'state', 'post_code', 'country',
            'created_at', 'updated_at', 'deleted_at',
        ];

        foreach ($expectedColumns as $expectedColumn) {
            $this->assertContains($expectedColumn, $columns);
        }
    }

    #[Test]
    public function it_can_create_a_table()
    {
        Schema::dropIfExists('foo');

        $this->assertFalse(Schema::hasTable('foo'));

        Schema::create('foo', function (Blueprint $table) {
            $table->string('bar');
        });

        $this->assertTrue(Schema::hasTable('foo'));

        // Clean up...
        Schema::drop('foo');
    }

    #[Test]
    public function it_can_create_a_table_with_an_identity_primary_key()
    {
        Schema::dropIfExists('foo');

        Schema::create('foo', function (Blueprint $table) {
            $table->id();
            $table->string('bar');
        });

        $this->assertSame(1, DB::table('foo')->insertGetId(['bar' => 'a']));
        $this->assertSame(2, DB::table('foo')->insertGetId(['bar' => 'b']));

        // Clean up...
        Schema::drop('foo');
    }

    #[Test]
    public function it_can_create_columns_with_modifiers()
    {
        Schema::dropIfExists('foo');

        Schema::create('foo', function (Blueprint $table) {
            $table->id();
            $table->string('name')->charset('UTF8')->collation('UNICODE_CI')->default("O'Brien");
            $table->enum('status', ['a', 'b'])->default('a');
            $table->timestamp('seen_at')->useCurrent();
        });

        DB::table('foo')->insert(['id' => 1]);

        $row = DB::table('foo')->first();

        $this->assertSame("O'Brien", $row->name);
        $this->assertSame('a', $row->status);
        $this->assertNotNull($row->seen_at);

        // Case-insensitive collation applies.
        $this->assertSame(1, DB::table('foo')->where('name', "o'brien")->count());

        // Enum check constraint applies.
        try {
            DB::table('foo')->update(['status' => 'c']);
            $this->fail('The enum check constraint was not applied.');
        } catch (\Illuminate\Database\QueryException) {
            // Expected.
        }

        // Clean up...
        Schema::drop('foo');
    }

    #[Test]
    public function it_can_add_multiple_columns()
    {
        DB::select('RECREATE TABLE "foo" ("id" INTEGER NOT NULL)');

        Schema::table('foo', function (Blueprint $table) {
            $table->string('a');
            $table->integer('b')->nullable();
        });

        $this->assertTrue(Schema::hasColumns('foo', ['id', 'a', 'b']));

        // Clean up...
        Schema::drop('foo');
    }

    #[Test]
    public function it_can_drop_and_rename_columns_and_indexes()
    {
        Schema::dropIfExists('foo');

        Schema::create('foo', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->index();
            $table->string('a');
            $table->string('b');
            $table->string('c');
        });

        $countIndexes = fn () => DB::scalar(
            'select count(*) from rdb$indices where rdb$relation_name = \'foo\''
        );

        // Primary key, unique and plain index.
        $this->assertEquals(3, $countIndexes());

        Schema::table('foo', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropIndex(['name']);
            $table->dropPrimary();
            $table->dropColumn(['a', 'b']);
            $table->renameColumn('c', 'd');
        });

        $this->assertEquals(0, $countIndexes());
        $this->assertFalse(Schema::hasColumn('foo', 'a'));
        $this->assertFalse(Schema::hasColumn('foo', 'b'));
        $this->assertFalse(Schema::hasColumn('foo', 'c'));
        $this->assertTrue(Schema::hasColumn('foo', 'd'));

        // Clean up...
        Schema::drop('foo');
    }

    #[Test]
    public function it_can_add_and_drop_foreign_keys_with_long_names()
    {
        Schema::dropIfExists('foo');
        Schema::dropIfExists('bar');

        // Reference a fresh table: adding a foreign key needs an exclusive lock on the
        // referenced table, which connections from earlier tests may still hold on "users".
        Schema::create('bar', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('foo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('a_really_long_user_reference_id');
            $table->foreign('a_really_long_user_reference_id')->references('id')->on('bar')->restrictOnDelete()->cascadeOnUpdate();
        });

        $countForeignKeys = fn () => DB::scalar(
            'select count(*) from rdb$relation_constraints where rdb$relation_name = \'foo\' and rdb$constraint_type = \'FOREIGN KEY\''
        );

        $this->assertEquals(1, $countForeignKeys());

        Schema::table('foo', function (Blueprint $table) {
            $table->dropForeign(['a_really_long_user_reference_id']);
        });

        $this->assertEquals(0, $countForeignKeys());

        // Clean up...
        Schema::drop('foo');
        Schema::drop('bar');
    }

    #[Test]
    public function it_gets_columns()
    {
        Schema::dropIfExists('foo');

        Schema::create('foo', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->default('x');
            $table->decimal('price', 8, 2)->nullable();
            $table->text('body')->nullable();
            $table->timestamp('seen_at')->nullable();
        });

        $columns = collect(Schema::getColumns('foo'))->keyBy('name');

        $this->assertSame(['id', 'name', 'price', 'body', 'seen_at'], $columns->keys()->all());

        $this->assertSame('bigint', $columns['id']['type']);
        $this->assertTrue($columns['id']['auto_increment']);
        $this->assertFalse($columns['id']['nullable']);

        $this->assertSame('varchar', $columns['name']['type_name']);
        $this->assertSame('varchar(100)', $columns['name']['type']);
        $this->assertSame("'x'", $columns['name']['default']);
        $this->assertFalse($columns['name']['nullable']);
        $this->assertFalse($columns['name']['auto_increment']);

        $this->assertSame('decimal(8,2)', $columns['price']['type']);
        $this->assertTrue($columns['price']['nullable']);
        $this->assertNull($columns['price']['default']);

        $this->assertSame('blob sub_type text', $columns['body']['type']);
        $this->assertSame('timestamp', $columns['seen_at']['type']);

        $this->assertSame('varchar', Schema::getColumnType('foo', 'name'));

        // Clean up...
        Schema::drop('foo');
    }

    #[Test]
    public function it_gets_indexes()
    {
        Schema::dropIfExists('foo');

        Schema::create('foo', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('first');
            $table->string('last');
            $table->unique('email');
            $table->index(['last', 'first']);
        });

        $indexes = collect(Schema::getIndexes('foo'))->keyBy('name');

        $primary = $indexes->firstWhere('primary', true);
        $this->assertSame(['id'], $primary['columns']);
        $this->assertTrue($primary['unique']);

        $this->assertSame(['email'], $indexes['foo_email_unique']['columns']);
        $this->assertTrue($indexes['foo_email_unique']['unique']);
        $this->assertFalse($indexes['foo_email_unique']['primary']);

        $this->assertSame(['last', 'first'], $indexes['foo_last_first_index']['columns']);
        $this->assertFalse($indexes['foo_last_first_index']['unique']);

        $this->assertTrue(Schema::hasIndex('foo', ['email'], 'unique'));
        $this->assertTrue(Schema::hasIndex('foo', 'foo_last_first_index'));
        $this->assertFalse(Schema::hasIndex('foo', ['first']));

        // Clean up...
        Schema::drop('foo');
    }

    #[Test]
    public function it_gets_foreign_keys()
    {
        $foreignKeys = Schema::getForeignKeys('orders');

        $this->assertSame([[
            'name' => 'orders_user_id_foreign',
            'columns' => ['user_id'],
            'foreign_schema' => null,
            'foreign_table' => 'users',
            'foreign_columns' => ['id'],
            'on_update' => 'no action',
            'on_delete' => 'no action',
        ]], $foreignKeys);
    }

    #[Test]
    public function it_can_change_columns()
    {
        Schema::dropIfExists('foo');

        Schema::create('foo', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->default('x');
            $table->decimal('price', 8, 2)->nullable();
        });

        DB::table('foo')->insert(['id' => 1, 'price' => 1.5]);

        Schema::table('foo', function (Blueprint $table) {
            $table->id()->change();
            $table->string('name', 100)->nullable()->change();
            $table->decimal('price', 10, 2)->default(0)->change();
        });

        $columns = collect(Schema::getColumns('foo'))->keyBy('name');

        $this->assertTrue($columns['id']['auto_increment']);
        $this->assertSame('varchar(100)', $columns['name']['type']);
        $this->assertTrue($columns['name']['nullable']);
        $this->assertNull($columns['name']['default']);
        $this->assertSame('decimal(10,2)', $columns['price']['type']);
        $this->assertFalse($columns['price']['nullable']);
        $this->assertSame("'0'", $columns['price']['default']);

        // Clean up...
        Schema::drop('foo');
    }

    #[Test]
    public function it_can_create_virtual_generated_columns()
    {
        Schema::dropIfExists('foo');

        Schema::create('foo', function (Blueprint $table) {
            $table->integer('a');
            $table->integer('b')->virtualAs('"a" * 2');
        });

        DB::table('foo')->insert(['a' => 21]);

        $this->assertEquals(42, DB::table('foo')->value('b'));

        $column = collect(Schema::getColumns('foo'))->firstWhere('name', 'b');
        $this->assertSame(['type' => 'virtual', 'expression' => '"a" * 2'], $column['generation']);

        // Clean up...
        Schema::drop('foo');
    }

    #[Test]
    public function it_creates_float_and_binary_columns()
    {
        Schema::dropIfExists('foo');

        Schema::create('foo', function (Blueprint $table) {
            $table->float('a');
            $table->float('b', 10);
            $table->binary('c');
            $table->binary('d', 16, fixed: true);
            $table->binary('e', 100);
        });

        $types = collect(Schema::getColumns('foo'))->pluck('type', 'name')->all();

        $this->assertSame([
            'a' => 'double precision',
            'b' => 'float',
            'c' => 'blob sub_type binary',
            'd' => 'binary(16)',
            'e' => 'varbinary(100)',
        ], $types);

        // Clean up...
        Schema::drop('foo');
    }

    #[Test]
    public function it_creates_time_zone_columns()
    {
        // pdo_firebird only handles time zone types when built against a Firebird 4+ client.
        $client = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_CLIENT_VERSION);

        // e.g. "LI-V6.3.11.33703 Firebird 3.0"
        if (preg_match('/Firebird (\d+)/', $client, $matches) && $matches[1] < 4) {
            $this->markTestSkipped("Time zone types require a Firebird 4+ client library, found [{$client}].");
        }

        Schema::dropIfExists('foo');

        Schema::create('foo', function (Blueprint $table) {
            $table->integer('id');
            $table->timestampTz('a')->useCurrent();
            $table->timestampTz('b')->nullable();
            $table->timeTz('c')->nullable();
        });

        DB::table('foo')->insert(['id' => 1, 'b' => '2026-10-06 11:00:00 +10:00', 'c' => '11:00:00 +10:00']);

        $row = DB::table('foo')->first();

        $this->assertNotNull($row->a);
        $this->assertSame('2026-10-06 11:00:00 +10:00', $row->b);
        $this->assertSame('11:00:00 +10:00', $row->c);

        $types = collect(Schema::getColumns('foo'))->pluck('type', 'name')->all();
        $this->assertSame('timestamp with time zone', $types['b']);
        $this->assertSame('time with time zone', $types['c']);

        // Clean up...
        Schema::drop('foo');
    }

    #[Test]
    public function it_creates_boolean_columns()
    {
        Schema::dropIfExists('foo');

        Schema::create('foo', function (Blueprint $table) {
            $table->integer('id');
            $table->boolean('active')->default(true);
            $table->boolean('admin')->nullable();
        });

        DB::table('foo')->insert(['id' => 1, 'admin' => false]);
        DB::table('foo')->insert(['id' => 2, 'active' => false, 'admin' => true]);

        $this->assertSame(true, DB::table('foo')->where('id', 1)->value('active'));
        $this->assertSame([1], DB::table('foo')->where('admin', false)->pluck('id')->all());
        $this->assertSame([2], DB::table('foo')->where('active', false)->pluck('id')->all());

        $this->assertSame('boolean', Schema::getColumnType('foo', 'active'));
        $this->assertSame('true', strtolower(collect(Schema::getColumns('foo'))->firstWhere('name', 'active')['default']));

        // Clean up...
        Schema::drop('foo');
    }

    #[Test]
    public function it_stores_large_json_values()
    {
        Schema::dropIfExists('foo');

        Schema::create('foo', function (Blueprint $table) {
            $table->json('data');
        });

        $json = json_encode(['text' => str_repeat('a', 20000)]);

        DB::table('foo')->insert(['data' => $json]);

        $this->assertSame($json, DB::table('foo')->value('data'));

        // Clean up...
        Schema::drop('foo');
    }

    #[Test]
    public function it_throws_an_exception_for_creating_temporary_tables()
    {
        Schema::dropIfExists('foo');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('This database driver does not support temporary tables.');

        $this->assertFalse(Schema::hasTable('foo'));

        Schema::create('foo', function (Blueprint $table) {
            $table->temporary();

            $table->string('bar');
        });

        $this->assertFalse(Schema::hasTable('foo'));
    }

    #[Test]
    public function it_can_drop_table()
    {
        DB::select('RECREATE TABLE "foo" ("id" INTEGER NOT NULL)');

        $this->assertTrue(Schema::hasTable('foo'));

        Schema::drop('foo');

        $this->assertFalse(Schema::hasTable('foo'));
    }

    #[Test]
    public function it_can_drop_table_if_exists()
    {
        DB::select('RECREATE TABLE "foo" ("id" INTEGER NOT NULL)');

        $this->assertTrue(Schema::hasTable('foo'));

        Schema::dropIfExists('foo');

        $this->assertFalse(Schema::hasTable('foo'));

        // Run again to check exists = false.

        Schema::dropIfExists('foo');

        $this->assertFalse(Schema::hasTable('foo'));
    }
}
