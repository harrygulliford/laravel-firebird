<?php

namespace HarryGulliford\Firebird\Tests\Unit;

use HarryGulliford\Firebird\Query\Grammars\FirebirdGrammar;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QueryGrammarTest extends TestCase
{
    protected FirebirdGrammar $grammar;

    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabaseName')->willReturn('test');
        $this->grammar = new FirebirdGrammar($this->connection);
    }

    protected function getBuilder(): Builder
    {
        return new Builder($this->connection, $this->grammar, new Processor);
    }

    // =========================================================================
    // LIMIT / OFFSET (FB 3.0+ syntax: FETCH FIRST / OFFSET ROWS)
    // =========================================================================

    #[Test]
    public function it_compiles_limit()
    {
        $builder = $this->getBuilder()->select('*')->from('users')->limit(10);
        $sql = $this->grammar->compileSelect($builder);

        $this->assertStringContainsString('fetch first 10 rows only', $sql);
    }

    #[Test]
    public function it_compiles_offset()
    {
        $builder = $this->getBuilder()->select('*')->from('users')->offset(5);
        $sql = $this->grammar->compileSelect($builder);

        $this->assertStringContainsString('offset 5 rows', $sql);
    }

    #[Test]
    public function it_compiles_limit_and_offset()
    {
        $builder = $this->getBuilder()->select('*')->from('users')->limit(10)->offset(5);
        $sql = $this->grammar->compileSelect($builder);

        $this->assertStringContainsString('offset 5 rows', $sql);
        $this->assertStringContainsString('fetch first 10 rows only', $sql);
        // offset must come before limit in Firebird
        $this->assertLessThan(
            strpos($sql, 'fetch first'),
            strpos($sql, 'offset')
        );
    }

    // =========================================================================
    // INSERT
    // =========================================================================

    #[Test]
    public function it_compiles_insert_single_row()
    {
        $builder = $this->getBuilder()->from('users');
        $sql = $this->grammar->compileInsert($builder, [['name' => 'test', 'email' => 'a@b.com']]);

        $this->assertStringContainsString('insert into', $sql);
        $this->assertStringNotContainsString(';', $sql);
    }

    #[Test]
    public function it_compiles_insert_multiple_rows_as_separate_statements()
    {
        $builder = $this->getBuilder()->from('users');
        $sql = $this->grammar->compileInsert($builder, [
            ['name' => 'a', 'email' => '1'],
            ['name' => 'b', 'email' => '2'],
            ['name' => 'c', 'email' => '3'],
        ]);

        // Firebird does not support multi-row VALUES — must be separate statements
        $this->assertEquals(2, substr_count($sql, ';'));
        $this->assertEquals(3, substr_count($sql, 'insert into'));
    }

    #[Test]
    public function it_compiles_insert_default_values()
    {
        $builder = $this->getBuilder()->from('users');
        $sql = $this->grammar->compileInsert($builder, []);

        $this->assertEquals('insert into "users" default values', $sql);
    }

    #[Test]
    public function it_compiles_insert_get_id_with_returning()
    {
        $builder = $this->getBuilder()->from('users');
        $sql = $this->grammar->compileInsertGetId($builder, [['name' => 'test']], 'id');

        $this->assertStringContainsString('returning "id"', $sql);
    }

    #[Test]
    public function it_compiles_insert_get_id_defaults_to_id()
    {
        $builder = $this->getBuilder()->from('users');
        $sql = $this->grammar->compileInsertGetId($builder, [['name' => 'test']], null);

        $this->assertStringContainsString('returning "id"', $sql);
    }

    // =========================================================================
    // TRUNCATE
    // =========================================================================

    #[Test]
    public function it_compiles_truncate_as_delete()
    {
        $builder = $this->getBuilder()->from('users');
        $result = $this->grammar->compileTruncate($builder);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('delete from "users"', $result);
    }

    // =========================================================================
    // LOCK
    // =========================================================================

    #[Test]
    public function it_compiles_lock_for_update()
    {
        $builder = $this->getBuilder()->select('*')->from('users')->lock(true);
        $sql = $this->grammar->compileSelect($builder);

        $this->assertStringContainsString('with lock', $sql);
    }

    #[Test]
    public function it_compiles_shared_lock_as_empty()
    {
        $builder = $this->getBuilder()->select('*')->from('users')->lock(false);
        $sql = $this->grammar->compileSelect($builder);

        $this->assertStringNotContainsString('with lock', $sql);
    }

    #[Test]
    public function it_compiles_custom_lock_string()
    {
        $builder = $this->getBuilder()->select('*')->from('users')->lock('with lock skip locked');
        $sql = $this->grammar->compileSelect($builder);

        $this->assertStringContainsString('with lock skip locked', $sql);
    }

    // =========================================================================
    // UPSERT (UPDATE OR INSERT)
    // =========================================================================

    #[Test]
    public function it_compiles_upsert()
    {
        $builder = $this->getBuilder()->from('users');
        $sql = $this->grammar->compileUpsert(
            $builder,
            [['id' => 1, 'name' => 'test']],
            ['id'],
            ['name']
        );

        $this->assertStringContainsString('update or insert into', $sql);
        $this->assertStringContainsString('matching ("id")', $sql);
    }

    #[Test]
    public function it_compiles_upsert_multiple_rows()
    {
        $builder = $this->getBuilder()->from('users');
        $sql = $this->grammar->compileUpsert(
            $builder,
            [['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => 'b']],
            ['id'],
            ['name']
        );

        $this->assertEquals(2, substr_count($sql, 'update or insert into'));
        $this->assertStringContainsString(';', $sql);
    }

    // =========================================================================
    // INSERT OR IGNORE
    // =========================================================================

    #[Test]
    public function it_compiles_insert_or_ignore()
    {
        $builder = $this->getBuilder()->from('users');
        $sql = $this->grammar->compileInsertOrIgnore(
            $builder,
            [['id' => 1, 'name' => 'test']]
        );

        $this->assertStringContainsString('update or insert into', $sql);
        $this->assertStringNotContainsString('matching', $sql);
    }

    // =========================================================================
    // MERGE
    // =========================================================================

    #[Test]
    public function it_compiles_merge()
    {
        $builder = $this->getBuilder()->from('users');
        $sql = $this->grammar->compileMerge(
            $builder,
            'target',
            'source',
            'target.id = source.id',
            'update set "name" = source."name"',
            'insert ("name") values (source."name")'
        );

        $this->assertStringStartsWith('merge into', $sql);
        $this->assertStringContainsString('using source on target.id = source.id', $sql);
        $this->assertStringContainsString('when matched then update', $sql);
        $this->assertStringContainsString('when not matched then insert', $sql);
    }

    #[Test]
    public function it_compiles_merge_without_not_matched()
    {
        $builder = $this->getBuilder()->from('users');
        $sql = $this->grammar->compileMerge(
            $builder, 'target', 'source', 'target.id = source.id',
            'update set "name" = source."name"'
        );

        $this->assertStringContainsString('when matched', $sql);
        $this->assertStringNotContainsString('when not matched', $sql);
    }

    // =========================================================================
    // RETURNING on UPDATE / DELETE
    // =========================================================================

    #[Test]
    public function it_compiles_update_returning()
    {
        $builder = $this->getBuilder()->from('users')->where('id', 1);
        $sql = $this->grammar->compileUpdateReturning($builder, ['name' => 'test'], ['id', 'name']);

        $this->assertStringContainsString('update', strtolower($sql));
        $this->assertStringContainsString('set', strtolower($sql));
        $this->assertStringContainsString('returning "id", "name"', $sql);
    }

    #[Test]
    public function it_compiles_update_returning_star()
    {
        $builder = $this->getBuilder()->from('users')->where('id', 1);
        $sql = $this->grammar->compileUpdateReturning($builder, ['name' => 'test'], '*');

        $this->assertStringEndsWith('returning *', $sql);
    }

    #[Test]
    public function it_compiles_delete_returning()
    {
        $builder = $this->getBuilder()->from('users')->where('id', 1);
        $sql = $this->grammar->compileDeleteReturning($builder, ['id', 'name']);

        $this->assertStringContainsString('delete from', strtolower($sql));
        $this->assertStringContainsString('returning "id", "name"', $sql);
    }

    #[Test]
    public function it_compiles_delete_returning_star()
    {
        $builder = $this->getBuilder()->from('users')->where('id', 1);
        $sql = $this->grammar->compileDeleteReturning($builder, '*');

        $this->assertStringEndsWith('returning *', $sql);
    }

    // =========================================================================
    // BITWISE
    // =========================================================================

    #[Test]
    public function it_compiles_bitwise_and()
    {
        $reflection = new \ReflectionMethod($this->grammar, 'whereBitwise');
        $reflection->setAccessible(true);
        $sql = $reflection->invoke($this->grammar, $this->getBuilder(), [
            'column' => 'flags', 'operator' => '&', 'value' => 4, 'boolean' => 'and',
        ]);

        $this->assertStringContainsString('bin_and("flags"', $sql);
    }

    #[Test]
    public function it_compiles_bitwise_or()
    {
        $reflection = new \ReflectionMethod($this->grammar, 'whereBitwise');
        $reflection->setAccessible(true);
        $sql = $reflection->invoke($this->grammar, $this->getBuilder(), [
            'column' => 'flags', 'operator' => '|', 'value' => 2, 'boolean' => 'and',
        ]);

        $this->assertStringContainsString('bin_or("flags"', $sql);
    }

    #[Test]
    public function it_compiles_bitwise_xor()
    {
        $reflection = new \ReflectionMethod($this->grammar, 'whereBitwise');
        $reflection->setAccessible(true);
        $sql = $reflection->invoke($this->grammar, $this->getBuilder(), [
            'column' => 'flags', 'operator' => '^', 'value' => 1, 'boolean' => 'and',
        ]);

        $this->assertStringContainsString('bin_xor("flags"', $sql);
    }

    // =========================================================================
    // EXISTS
    // =========================================================================

    #[Test]
    public function it_compiles_exists_from_rdb_database()
    {
        $builder = $this->getBuilder()->select('*')->from('users');
        $sql = $this->grammar->compileExists($builder);

        $this->assertStringContainsString('exists(', $sql);
        $this->assertStringContainsString('rdb$database', $sql);
    }

    // =========================================================================
    // RANDOM
    // =========================================================================

    #[Test]
    public function it_compiles_random()
    {
        $this->assertEquals('rand()', $this->grammar->compileRandom(''));
    }

    // =========================================================================
    // UNION
    // =========================================================================

    #[Test]
    public function it_wraps_union_in_derived_table()
    {
        $builder = $this->getBuilder()->select('*')->from('users');
        $reflection = new \ReflectionMethod($this->grammar, 'wrapUnion');
        $reflection->setAccessible(true);
        $sql = $reflection->invoke($this->grammar, $this->grammar->compileSelect($builder));

        $this->assertStringStartsWith('select * from (', $sql);
    }

    // =========================================================================
    // OPERATORS
    // =========================================================================

    #[Test]
    public function it_includes_firebird_operators()
    {
        $operators = $this->grammar->getOperators();

        $this->assertContains('containing', $operators);
        $this->assertContains('not containing', $operators);
        $this->assertContains('starting with', $operators);
        $this->assertContains('not starting with', $operators);
        $this->assertContains('similar to', $operators);
        $this->assertContains('not similar to', $operators);
        $this->assertContains('is distinct from', $operators);
        $this->assertContains('is not distinct from', $operators);
    }
}
