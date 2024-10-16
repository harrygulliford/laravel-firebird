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
