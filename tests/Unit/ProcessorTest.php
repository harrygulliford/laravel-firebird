<?php

namespace HarryGulliford\Firebird\Tests\Unit;

use HarryGulliford\Firebird\Query\Processors\FirebirdProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProcessorTest extends TestCase
{
    protected FirebirdProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new FirebirdProcessor;
    }

    // =========================================================================
    // mapFieldType
    // =========================================================================

    #[Test]
    public function it_maps_smallint()
    {
        $reflection = new \ReflectionMethod($this->processor, 'mapFieldType');
        $reflection->setAccessible(true);

        $this->assertEquals('smallint', $reflection->invoke($this->processor, 7, 0, 2, 0, 0));
    }

    #[Test]
    public function it_maps_smallint_with_scale_to_numeric()
    {
        $reflection = new \ReflectionMethod($this->processor, 'mapFieldType');
        $reflection->setAccessible(true);

        $this->assertEquals('numeric(4, 2)', $reflection->invoke($this->processor, 7, 0, 2, 4, -2));
    }

    #[Test]
    public function it_maps_integer()
    {
        $reflection = new \ReflectionMethod($this->processor, 'mapFieldType');
        $reflection->setAccessible(true);

        $this->assertEquals('integer', $reflection->invoke($this->processor, 8, 0, 4, 0, 0));
    }

    #[Test]
    public function it_maps_bigint()
    {
        $reflection = new \ReflectionMethod($this->processor, 'mapFieldType');
        $reflection->setAccessible(true);

        $this->assertEquals('bigint', $reflection->invoke($this->processor, 16, 0, 8, 0, 0));
    }

    #[Test]
    public function it_maps_bigint_numeric_subtype()
    {
        $reflection = new \ReflectionMethod($this->processor, 'mapFieldType');
        $reflection->setAccessible(true);

        // subtype 1 = NUMERIC, subtype 2 = DECIMAL
        $this->assertEquals('numeric(15, 2)', $reflection->invoke($this->processor, 16, 1, 8, 15, -2));
        $this->assertEquals('decimal(15, 2)', $reflection->invoke($this->processor, 16, 2, 8, 15, -2));
    }

    #[Test]
    public function it_maps_float_and_double()
    {
        $reflection = new \ReflectionMethod($this->processor, 'mapFieldType');
        $reflection->setAccessible(true);

        $this->assertEquals('float', $reflection->invoke($this->processor, 10, 0, 4, 0, 0));
        $this->assertEquals('double precision', $reflection->invoke($this->processor, 27, 0, 8, 0, 0));
    }

    #[Test]
    public function it_maps_char_and_varchar()
    {
        $reflection = new \ReflectionMethod($this->processor, 'mapFieldType');
        $reflection->setAccessible(true);

        $this->assertEquals('char(10)', $reflection->invoke($this->processor, 14, 0, 10, 0, 0));
        $this->assertEquals('varchar(255)', $reflection->invoke($this->processor, 37, 0, 255, 0, 0));
    }

    #[Test]
    public function it_maps_date_time_types()
    {
        $reflection = new \ReflectionMethod($this->processor, 'mapFieldType');
        $reflection->setAccessible(true);

        $this->assertEquals('date', $reflection->invoke($this->processor, 12, 0, 4, 0, 0));
        $this->assertEquals('time', $reflection->invoke($this->processor, 13, 0, 4, 0, 0));
        $this->assertEquals('timestamp', $reflection->invoke($this->processor, 35, 0, 8, 0, 0));
    }

    #[Test]
    public function it_maps_boolean()
    {
        $reflection = new \ReflectionMethod($this->processor, 'mapFieldType');
        $reflection->setAccessible(true);

        $this->assertEquals('boolean', $reflection->invoke($this->processor, 23, 0, 1, 0, 0));
    }

    #[Test]
    public function it_maps_blob_types()
    {
        $reflection = new \ReflectionMethod($this->processor, 'mapFieldType');
        $reflection->setAccessible(true);

        $this->assertEquals('blob sub_type binary', $reflection->invoke($this->processor, 261, 0, 8, 0, 0));
        $this->assertEquals('blob sub_type text', $reflection->invoke($this->processor, 261, 1, 8, 0, 0));
    }

    #[Test]
    public function it_maps_fb4_types()
    {
        $reflection = new \ReflectionMethod($this->processor, 'mapFieldType');
        $reflection->setAccessible(true);

        // DECFLOAT (FB 4.0+)
        $this->assertEquals('decfloat(16)', $reflection->invoke($this->processor, 24, 0, 8, 0, 0));
        $this->assertEquals('decfloat(34)', $reflection->invoke($this->processor, 25, 0, 16, 0, 0));

        // INT128 (FB 4.0+)
        $this->assertEquals('int128', $reflection->invoke($this->processor, 26, 0, 16, 0, 0));

        // TZ types (FB 4.0+)
        $this->assertEquals('time with time zone', $reflection->invoke($this->processor, 28, 0, 8, 0, 0));
        $this->assertEquals('timestamp with time zone', $reflection->invoke($this->processor, 29, 0, 12, 0, 0));
    }

    #[Test]
    public function it_maps_unknown_type()
    {
        $reflection = new \ReflectionMethod($this->processor, 'mapFieldType');
        $reflection->setAccessible(true);

        $this->assertEquals('unknown(999)', $reflection->invoke($this->processor, 999, 0, 0, 0, 0));
    }

    // =========================================================================
    // processColumns
    // =========================================================================

    #[Test]
    public function it_processes_columns()
    {
        $results = [
            (object) [
                'name' => 'ID    ',
                'field_type' => 8,
                'field_sub_type' => 0,
                'field_length' => 4,
                'field_precision' => 0,
                'field_scale' => 0,
                'null_flag' => 1,
                'default_source' => null,
                'computed_source' => null,
                'description' => null,
                'identity_type' => 1,
                'collation_name' => null,
            ],
            (object) [
                'name' => 'NAME  ',
                'field_type' => 37,
                'field_sub_type' => 0,
                'field_length' => 100,
                'field_precision' => 0,
                'field_scale' => 0,
                'null_flag' => null,
                'default_source' => "DEFAULT 'unknown'",
                'computed_source' => null,
                'description' => 'User name',
                'identity_type' => null,
                'collation_name' => 'UTF8  ',
            ],
        ];

        $columns = $this->processor->processColumns($results);

        $this->assertCount(2, $columns);

        // ID column
        $this->assertEquals('ID', $columns[0]['name']);
        $this->assertEquals('integer', $columns[0]['type_name']);
        $this->assertFalse($columns[0]['nullable']);
        $this->assertTrue($columns[0]['auto_increment']);
        $this->assertNull($columns[0]['generation']);

        // NAME column
        $this->assertEquals('NAME', $columns[1]['name']);
        $this->assertEquals('varchar(100)', $columns[1]['type_name']);
        $this->assertTrue($columns[1]['nullable']);
        $this->assertFalse($columns[1]['auto_increment']);
        $this->assertEquals("DEFAULT 'unknown'", $columns[1]['default']);
        $this->assertEquals('User name', $columns[1]['comment']);
        $this->assertEquals('UTF8', $columns[1]['collation']);
    }

    // =========================================================================
    // processIndexes
    // =========================================================================

    #[Test]
    public function it_processes_indexes()
    {
        $results = [
            (object) ['name' => 'PK_USERS  ', 'columns' => 'ID', 'unique_flag' => 1, 'is_primary' => 1],
            (object) ['name' => 'IDX_EMAIL ', 'columns' => 'EMAIL', 'unique_flag' => 0, 'is_primary' => 0],
        ];

        $indexes = $this->processor->processIndexes($results);

        $this->assertCount(2, $indexes);
        $this->assertEquals('pk_users', $indexes[0]['name']);
        $this->assertTrue($indexes[0]['primary']);
        $this->assertTrue($indexes[0]['unique']);
        $this->assertEquals('idx_email', $indexes[1]['name']);
        $this->assertFalse($indexes[1]['primary']);
    }

    // =========================================================================
    // processForeignKeys
    // =========================================================================

    #[Test]
    public function it_processes_foreign_keys()
    {
        $results = [
            (object) [
                'name' => 'FK_USER_ID  ',
                'columns' => 'USER_ID',
                'foreign_table' => 'USERS  ',
                'foreign_columns' => 'ID',
                'update_rule' => 'NO ACTION  ',
                'delete_rule' => 'CASCADE    ',
            ],
        ];

        $fks = $this->processor->processForeignKeys($results);

        $this->assertCount(1, $fks);
        $this->assertEquals('fk_user_id', $fks[0]['name']);
        $this->assertEquals(['user_id'], $fks[0]['columns']);
        $this->assertEquals('users', $fks[0]['foreign_table']);
        $this->assertEquals(['id'], $fks[0]['foreign_columns']);
        $this->assertEquals('no action', $fks[0]['on_update']);
        $this->assertEquals('cascade', $fks[0]['on_delete']);
    }
}
