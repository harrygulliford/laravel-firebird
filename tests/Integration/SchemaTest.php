<?php

namespace HarryGulliford\Firebird\Tests\Integration;

use HarryGulliford\Firebird\Tests\TestCase;

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

    #[Test]
    public function it_gets_columns_with_metadata()
    {
        $columns = Schema::getColumns('users');

        $this->assertIsArray($columns);
        $this->assertNotEmpty($columns);

        $idColumn = collect($columns)->firstWhere('name', 'id');
        $this->assertNotNull($idColumn);
        $this->assertArrayHasKey('type_name', $idColumn);
        $this->assertArrayHasKey('nullable', $idColumn);
        $this->assertArrayHasKey('default', $idColumn);
        $this->assertArrayHasKey('auto_increment', $idColumn);
        $this->assertFalse($idColumn['nullable']);
    }

    #[Test]
    public function it_gets_views()
    {
        DB::statement('CREATE OR ALTER VIEW "test_view" AS SELECT "id", "name" FROM "users"');

        $views = Schema::getViews();
        $this->assertIsArray($views);
        $viewNames = array_column($views, 'name');
        $this->assertContains('test_view', array_map('strtolower', array_map('trim', $viewNames)));

        DB::statement('DROP VIEW "test_view"');
    }

    #[Test]
    public function it_gets_indexes()
    {
        $indexes = Schema::getIndexes('users');

        $this->assertIsArray($indexes);
        $this->assertNotEmpty($indexes);

        // Should have at least the primary key index
        $primary = collect($indexes)->firstWhere('primary', true);
        $this->assertNotNull($primary);
    }

    #[Test]
    public function it_gets_foreign_keys()
    {
        $foreignKeys = Schema::getForeignKeys('orders');

        $this->assertIsArray($foreignKeys);
        $this->assertNotEmpty($foreignKeys);

        $fk = $foreignKeys[0];
        $this->assertArrayHasKey('columns', $fk);
        $this->assertArrayHasKey('foreign_table', $fk);
        $this->assertArrayHasKey('foreign_columns', $fk);
        $this->assertArrayHasKey('on_update', $fk);
        $this->assertArrayHasKey('on_delete', $fk);
    }

    #[Test]
    public function it_can_add_column()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable();
        });

        $this->assertTrue(Schema::hasColumn('users', 'phone'));

        // Clean up
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });

        $this->assertFalse(Schema::hasColumn('users', 'phone'));
    }

    #[Test]
    public function it_can_drop_column()
    {
        DB::statement('ALTER TABLE "users" ADD "temp_col" INTEGER');
        $this->assertTrue(Schema::hasColumn('users', 'temp_col'));

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('temp_col');
        });

        $this->assertFalse(Schema::hasColumn('users', 'temp_col'));
    }

    #[Test]
    public function it_can_rename_column()
    {
        DB::statement('ALTER TABLE "users" ADD "old_name" VARCHAR(50)');
        $this->assertTrue(Schema::hasColumn('users', 'old_name'));

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('old_name', 'new_name');
        });

        $this->assertFalse(Schema::hasColumn('users', 'old_name'));
        $this->assertTrue(Schema::hasColumn('users', 'new_name'));

        // Clean up
        DB::statement('ALTER TABLE "users" DROP "new_name"');
    }

    #[Test]
    public function it_can_create_and_drop_index()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('email', 'idx_users_email');
        });

        $indexes = Schema::getIndexes('users');
        $indexNames = array_map(fn($i) => $i['name'], $indexes);
        $this->assertContains('idx_users_email', $indexNames);

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_email');
        });

        $indexes = Schema::getIndexes('users');
        $indexNames = array_map(fn($i) => $i['name'], $indexes);
        $this->assertNotContains('idx_users_email', $indexNames);
    }

    #[Test]
    public function it_can_create_table_with_identity()
    {
        Schema::dropIfExists('test_identity');

        Schema::create('test_identity', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $this->assertTrue(Schema::hasTable('test_identity'));

        // Insert and check auto-increment
        $id = DB::table('test_identity')->insertGetId(['name' => 'test'], 'id');
        $this->assertNotNull($id);
        $this->assertGreaterThan(0, $id);

        Schema::drop('test_identity');
    }

    #[Test]
    public function it_can_create_temporary_table()
    {
        Schema::dropIfExists('temp_test');

        Schema::create('temp_test', function (Blueprint $table) {
            $table->temporary();
            $table->id();
            $table->string('data');
        });

        $this->assertTrue(Schema::hasTable('temp_test'));

        Schema::drop('temp_test');
    }

    #[Test]
    public function it_supports_ddl_transactions()
    {
        $grammar = Schema::getConnection()->getSchemaGrammar();
        $this->assertTrue($grammar->supportsSchemaTransactions());
    }
}
