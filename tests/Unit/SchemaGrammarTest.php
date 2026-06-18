<?php

namespace HarryGulliford\Firebird\Tests\Unit;

use HarryGulliford\Firebird\Schema\Grammars\FirebirdGrammar;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SchemaGrammarTest extends TestCase
{
    protected FirebirdGrammar $grammar;

    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabaseName')->willReturn('test');
        $this->grammar = new FirebirdGrammar($this->connection);
        $this->connection->method('getSchemaGrammar')->willReturn($this->grammar);
    }

    protected function getBlueprint(string $table): Blueprint
    {
        return new Blueprint($this->connection, $table);
    }

    protected function callType(string $typeName, array $params = []): string
    {
        $method = 'type'.ucfirst($typeName);
        $reflection = new \ReflectionMethod($this->grammar, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->grammar, new Fluent($params));
    }

    // =========================================================================
    // DDL TRANSACTIONS
    // =========================================================================

    #[Test]
    public function it_supports_schema_transactions()
    {
        $this->assertTrue($this->grammar->supportsSchemaTransactions());
    }

    // =========================================================================
    // INTROSPECTION QUERIES
    // =========================================================================

    #[Test]
    public function it_compiles_table_exists()
    {
        $sql = $this->grammar->compileTableExists(null, 'USERS');
        $this->assertStringContainsString('rdb$relations', $sql);
        $this->assertStringContainsString('exists', $sql);
    }

    #[Test]
    public function it_compiles_tables()
    {
        $sql = $this->grammar->compileTables(null);
        $this->assertStringContainsString('rdb$relations', $sql);
        $this->assertStringContainsString('rdb$relation_type = 0', $sql);
    }

    #[Test]
    public function it_compiles_views()
    {
        $sql = $this->grammar->compileViews(null);
        $this->assertStringContainsString('rdb$relations', $sql);
        $this->assertStringContainsString('rdb$relation_type = 1', $sql);
    }

    #[Test]
    public function it_compiles_columns_with_full_metadata()
    {
        $sql = $this->grammar->compileColumns(null, 'USERS');
        $this->assertStringContainsString('rdb$field_type', $sql);
        $this->assertStringContainsString('rdb$field_scale', $sql);
        $this->assertStringContainsString('rdb$null_flag', $sql);
        $this->assertStringContainsString('rdb$default_source', $sql);
        $this->assertStringContainsString('rdb$computed_source', $sql);
        $this->assertStringContainsString('rdb$identity_type', $sql);
    }

    #[Test]
    public function it_compiles_indexes()
    {
        $sql = $this->grammar->compileIndexes(null, 'USERS');
        $this->assertStringContainsString('rdb$indices', $sql);
        $this->assertStringContainsString('rdb$index_segments', $sql);
        $this->assertStringContainsString('is_primary', $sql);
    }

    #[Test]
    public function it_compiles_foreign_keys()
    {
        $sql = $this->grammar->compileForeignKeys(null, 'ORDERS');
        $this->assertStringContainsString('rdb$ref_constraints', $sql);
        $this->assertStringContainsString('update_rule', $sql);
        $this->assertStringContainsString('delete_rule', $sql);
    }

    // =========================================================================
    // CREATE / DROP TABLE
    // =========================================================================

    #[Test]
    public function it_compiles_create_table()
    {
        $blueprint = $this->getBlueprint('test_table');
        $blueprint->integer('id');
        $blueprint->string('name', 100);

        $sql = $this->grammar->compileCreate($blueprint, new Fluent);
        $this->assertStringStartsWith('create table', $sql);
        $this->assertStringContainsString('test_table', $sql);
    }

    #[Test]
    public function it_compiles_create_temporary_table()
    {
        $blueprint = $this->getBlueprint('temp_table');
        $blueprint->temporary();
        $blueprint->integer('id');

        $sql = $this->grammar->compileCreate($blueprint, new Fluent);
        $this->assertStringContainsString('global temporary table', $sql);
        $this->assertStringContainsString('on commit preserve rows', $sql);
    }

    #[Test]
    public function it_compiles_drop()
    {
        $blueprint = $this->getBlueprint('users');
        $sql = $this->grammar->compileDrop($blueprint, new Fluent);
        $this->assertStringContainsString('drop table', strtolower($sql));
    }

    #[Test]
    public function it_compiles_drop_if_exists_with_execute_block()
    {
        $blueprint = $this->getBlueprint('users');
        $sql = $this->grammar->compileDropIfExists($blueprint, new Fluent);
        $this->assertStringContainsString('execute block', $sql);
        $this->assertStringContainsString('rdb$relations', $sql);
    }

    // =========================================================================
    // ALTER TABLE
    // =========================================================================

    #[Test]
    public function it_compiles_rename_column()
    {
        $blueprint = $this->getBlueprint('users');
        $sql = $this->grammar->compileRenameColumn($blueprint, new Fluent([
            'from' => 'old_name', 'to' => 'new_name',
        ]));
        $this->assertStringContainsString('alter column', strtolower($sql));
        $this->assertStringContainsString('"old_name" to "new_name"', $sql);
    }

    #[Test]
    public function it_compiles_drop_column()
    {
        $blueprint = $this->getBlueprint('users');
        $sql = $this->grammar->compileDropColumn($blueprint, new Fluent([
            'columns' => ['temp_col'],
        ]));
        $this->assertStringContainsString('alter table', strtolower($sql));
        $this->assertStringContainsString('drop', strtolower($sql));
    }

    // =========================================================================
    // CONSTRAINTS
    // =========================================================================

    #[Test]
    public function it_compiles_primary_key()
    {
        $blueprint = $this->getBlueprint('users');
        $sql = $this->grammar->compilePrimary($blueprint, new Fluent(['columns' => ['id']]));
        $this->assertStringContainsString('primary key', strtolower($sql));
    }

    #[Test]
    public function it_compiles_index()
    {
        $blueprint = $this->getBlueprint('users');
        $sql = $this->grammar->compileIndex($blueprint, new Fluent([
            'index' => 'idx_email', 'columns' => ['email'],
        ]));
        $this->assertStringStartsWith('create index', strtolower($sql));
    }

    #[Test]
    public function it_compiles_foreign_key()
    {
        $blueprint = $this->getBlueprint('orders');
        $sql = $this->grammar->compileForeign($blueprint, new Fluent([
            'index' => 'fk_user', 'columns' => ['user_id'],
            'on' => 'users', 'references' => ['id'],
            'onDelete' => 'cascade', 'onUpdate' => null,
        ]));
        $this->assertStringContainsString('foreign key', strtolower($sql));
        $this->assertStringContainsString('on delete cascade', strtolower($sql));
    }

    #[Test]
    public function it_compiles_drop_primary_with_execute_block()
    {
        $blueprint = $this->getBlueprint('users');
        $sql = $this->grammar->compileDropPrimary($blueprint, new Fluent);
        $this->assertStringContainsString('execute block', $sql);
        $this->assertStringContainsString('PRIMARY KEY', $sql);
    }

    #[Test]
    public function it_compiles_drop_index()
    {
        $blueprint = $this->getBlueprint('users');
        $sql = $this->grammar->compileDropIndex($blueprint, new Fluent(['index' => 'idx_email']));
        $this->assertStringContainsString('drop index', strtolower($sql));
    }

    // =========================================================================
    // SEQUENCES
    // =========================================================================

    #[Test]
    public function it_compiles_create_sequence()
    {
        $sql = $this->grammar->compileCreateSequence('gen_users_id', 0, 1);
        $this->assertEquals('create sequence gen_users_id start with 0 increment by 1', $sql);
    }

    #[Test]
    public function it_compiles_drop_sequence()
    {
        $this->assertEquals('drop sequence gen_test', $this->grammar->compileDropSequence('gen_test'));
    }

    #[Test]
    public function it_compiles_restart_sequence()
    {
        $this->assertEquals('alter sequence gen_test restart with 100', $this->grammar->compileRestartSequence('gen_test', 100));
    }

    // =========================================================================
    // TYPE MAPPINGS (via reflection on protected type* methods)
    // =========================================================================

    #[Test]
    public function it_maps_integer_types()
    {
        $this->assertEquals('integer', $this->callType('integer'));
        $this->assertEquals('bigint', $this->callType('bigInteger'));
        $this->assertEquals('smallint', $this->callType('smallInteger'));
        $this->assertEquals('smallint', $this->callType('tinyInteger'));
        $this->assertEquals('integer', $this->callType('mediumInteger'));
    }

    #[Test]
    public function it_maps_float_types()
    {
        $this->assertEquals('float', $this->callType('float'));
        $this->assertEquals('double precision', $this->callType('double'));
        $this->assertEquals('decimal(10, 2)', $this->callType('decimal', ['total' => 10, 'places' => 2]));
    }

    #[Test]
    public function it_maps_string_types()
    {
        $this->assertEquals('varchar(255)', $this->callType('string', ['length' => 255]));
        $this->assertEquals('char(10)', $this->callType('char', ['length' => 10]));
    }

    #[Test]
    public function it_maps_text_types_to_blob()
    {
        $this->assertEquals('blob sub_type text', $this->callType('text'));
        $this->assertEquals('blob sub_type text', $this->callType('mediumText'));
        $this->assertEquals('blob sub_type text', $this->callType('longText'));
        $this->assertEquals('blob sub_type text', $this->callType('tinyText'));
    }

    #[Test]
    public function it_maps_date_time_types()
    {
        $this->assertEquals('date', $this->callType('date'));
        $this->assertEquals('time', $this->callType('time'));
        $this->assertEquals('timestamp', $this->callType('dateTime'));
        $this->assertEquals('timestamp', $this->callType('timestamp', ['useCurrent' => false]));
    }

    #[Test]
    public function it_maps_boolean_type()
    {
        // Default server_version >= 3 → native BOOLEAN
        $this->assertEquals('boolean', $this->callType('boolean'));
    }

    #[Test]
    public function it_maps_json_to_blob_text()
    {
        $this->assertEquals('blob sub_type text', $this->callType('json'));
        $this->assertEquals('blob sub_type text', $this->callType('jsonb'));
    }

    #[Test]
    public function it_maps_binary_and_uuid()
    {
        $this->assertEquals('blob sub_type binary', $this->callType('binary'));
        $this->assertEquals('char(36)', $this->callType('uuid'));
    }

    #[Test]
    public function it_maps_network_types()
    {
        $this->assertEquals('varchar(45)', $this->callType('ipAddress'));
        $this->assertEquals('varchar(17)', $this->callType('macAddress'));
    }

    #[Test]
    public function it_maps_year_to_smallint()
    {
        $this->assertEquals('smallint', $this->callType('year'));
    }

    // =========================================================================
    // MODIFIERS (via reflection)
    // =========================================================================

    #[Test]
    public function it_compiles_increment_modifier()
    {
        $blueprint = $this->getBlueprint('test');
        $column = new Fluent(['type' => 'integer', 'autoIncrement' => true]);

        $reflection = new \ReflectionMethod($this->grammar, 'modifyIncrement');
        $reflection->setAccessible(true);

        $this->assertEquals(' generated by default as identity', $reflection->invoke($this->grammar, $blueprint, $column));
    }

    #[Test]
    public function it_does_not_add_increment_for_non_serial_types()
    {
        $blueprint = $this->getBlueprint('test');
        $column = new Fluent(['type' => 'string', 'autoIncrement' => true]);

        $reflection = new \ReflectionMethod($this->grammar, 'modifyIncrement');
        $reflection->setAccessible(true);

        $this->assertNull($reflection->invoke($this->grammar, $blueprint, $column));
    }
}
