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

        Schema::create('foo', function (Blueprint $table) {
            $table->id();
            $table->integer('a_really_long_user_reference_id');
            $table->foreign('a_really_long_user_reference_id')->references('id')->on('users');
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
