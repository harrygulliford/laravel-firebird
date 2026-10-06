<?php

namespace HarryGulliford\Firebird\Schema\Grammars;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

use function Illuminate\Support\enum_value;

class FirebirdGrammar extends Grammar
{
    /**
     * The possible column modifiers, in the order Firebird expects them:
     * colname type [CHARACTER SET] [DEFAULT] [constraints] [COLLATE].
     *
     * @var array
     */
    protected $modifiers = ['Charset', 'Increment', 'Default', 'Nullable', 'Check', 'Collate'];

    /**
     * The columns available as serials.
     *
     * @var array
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * Compile the query to determine if the given table exists.
     *
     * @param  string|null  $schema
     * @param  string  $table
     * @return string
     */
    public function compileTableExists($schema, $table)
    {
        return sprintf(
            'select case when exists (select 1 from rdb$relations where rdb$relation_name = %s and '
            .'rdb$relation_type = 0 and (rdb$system_flag is null or rdb$system_flag = 0)) then 1 else 0 end '
            .'as "exists" from rdb$database',
            $this->quoteString($table),
        );
    }

    /**
     * Compile the query to determine the tables.
     *
     * @param  string|string[]|null  $schema
     * @return string
     */
    public function compileTables($schema)
    {
        return 'select trim(trailing from rdb$relation_name) as "name" '
            .'from rdb$relations '
            .'where rdb$relation_type = 0 '
            .'and (rdb$system_flag is null or rdb$system_flag = 0) '
            .'order by rdb$relation_name';
    }

    /**
     * Compile the query to determine the columns.
     *
     * @param  string|null  $schema
     * @param  string  $table
     * @return string
     */
    public function compileColumns($schema, $table)
    {
        return sprintf(
            'select trim(rf.rdb$field_name) as "name", f.rdb$field_type as "field_type", '
            .'f.rdb$field_sub_type as "field_sub_type", f.rdb$character_length as "length", '
            .'f.rdb$field_precision as "precision", f.rdb$field_scale as "scale", '
            .'trim(cs.rdb$character_set_name) as "charset", trim(co.rdb$collation_name) as "collation", '
            .'coalesce(rf.rdb$null_flag, f.rdb$null_flag, 0) as "not_null", '
            .'coalesce(rf.rdb$default_source, f.rdb$default_source) as "default", '
            .'rf.rdb$identity_type as "identity_type", f.rdb$computed_source as "computed", '
            .'rf.rdb$description as "comment" '
            .'from rdb$relation_fields rf '
            .'join rdb$fields f on f.rdb$field_name = rf.rdb$field_source '
            .'left join rdb$character_sets cs on cs.rdb$character_set_id = f.rdb$character_set_id '
            .'left join rdb$collations co on co.rdb$character_set_id = f.rdb$character_set_id '
            .'and co.rdb$collation_id = coalesce(rf.rdb$collation_id, f.rdb$collation_id) '
            .'where rf.rdb$relation_name = %s '
            .'order by rf.rdb$field_position',
            $this->quoteString($table),
        );
    }

    /**
     * Compile the query to determine the indexes.
     *
     * Returns one row per index column, grouped by the processor.
     *
     * @param  string|null  $schema
     * @param  string  $table
     * @return string
     */
    public function compileIndexes($schema, $table)
    {
        return sprintf(
            'select trim(i.rdb$index_name) as "name", trim(s.rdb$field_name) as "column", '
            .'i.rdb$unique_flag as "unique", trim(rc.rdb$constraint_type) as "constraint_type" '
            .'from rdb$indices i '
            .'left join rdb$index_segments s on s.rdb$index_name = i.rdb$index_name '
            .'left join rdb$relation_constraints rc on rc.rdb$index_name = i.rdb$index_name '
            .'where i.rdb$relation_name = %s and (i.rdb$system_flag is null or i.rdb$system_flag = 0) '
            .'order by i.rdb$index_name, s.rdb$field_position',
            $this->quoteString($table),
        );
    }

    /**
     * Compile the query to determine the foreign keys.
     *
     * Returns one row per foreign key column, grouped by the processor.
     *
     * @param  string|null  $schema
     * @param  string  $table
     * @return string
     */
    public function compileForeignKeys($schema, $table)
    {
        return sprintf(
            'select trim(rc.rdb$constraint_name) as "name", trim(s.rdb$field_name) as "column", '
            .'trim(uq.rdb$relation_name) as "foreign_table", trim(us.rdb$field_name) as "foreign_column", '
            .'trim(ref.rdb$update_rule) as "on_update", trim(ref.rdb$delete_rule) as "on_delete" '
            .'from rdb$relation_constraints rc '
            .'join rdb$ref_constraints ref on ref.rdb$constraint_name = rc.rdb$constraint_name '
            .'join rdb$relation_constraints uq on uq.rdb$constraint_name = ref.rdb$const_name_uq '
            .'join rdb$index_segments s on s.rdb$index_name = rc.rdb$index_name '
            .'join rdb$index_segments us on us.rdb$index_name = uq.rdb$index_name '
            .'and us.rdb$field_position = s.rdb$field_position '
            .'where rc.rdb$relation_name = %s and rc.rdb$constraint_type = \'FOREIGN KEY\' '
            .'order by rc.rdb$constraint_name, s.rdb$field_position',
            $this->quoteString($table),
        );
    }

    /**
     * Compile a create table command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command)
    {
        if ($blueprint->temporary) {
            throw new \LogicException('This database driver does not support temporary tables.');
        }

        $columns = implode(', ', $this->getColumns($blueprint));

        $sql = 'create table '.$this->wrapTable($blueprint)." ($columns)";

        return $sql;
    }

    /**
     * Compile a drop table command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'execute block as begin if (exists(select 1 from rdb$relations where rdb$relation_name = %s and rdb$relation_type = 0 and '
            .'(rdb$system_flag is null or rdb$system_flag = 0))) then execute statement %s; end',
            $this->quoteString($this->getPrefixedTable($blueprint)),
            $this->quoteString('drop table '.$this->wrapTable($blueprint))
        );
    }

    /**
     * Compile a column addition command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('ALTER TABLE %s ADD %s',
            $this->wrapTable($blueprint),
            $this->getColumn($blueprint, $command->column)
        );
    }

    /**
     * Compile a primary key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('ALTER TABLE %s ADD CONSTRAINT %s PRIMARY KEY (%s)',
            $this->wrapTable($blueprint),
            $this->wrap($command->index),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a unique key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);

        $index = $this->wrap($command->index);

        $columns = $this->columnize($command->columns);

        return "ALTER TABLE {$table} ADD CONSTRAINT {$index} UNIQUE ({$columns})";
    }

    /**
     * Compile a plain index key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->columnize($command->columns);

        $index = $this->wrap($command->index);

        $table = $this->wrapTable($blueprint);

        return "CREATE INDEX {$index} ON {$table} ($columns)";
    }

    /**
     * Compile a foreign key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileForeign(Blueprint $blueprint, Fluent $command)
    {
        // Firebird has no RESTRICT action. NO ACTION is equivalent, as constraints aren't deferrable.
        foreach (['onDelete', 'onUpdate'] as $action) {
            if (strtolower((string) $command->{$action}) === 'restrict') {
                $command->{$action} = 'no action';
            }
        }

        return parent::compileForeign($blueprint, $command);
    }

    /**
     * Compile a drop foreign key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileDropUnique($blueprint, $command);
    }

    /**
     * Compile a drop primary key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command)
    {
        // Inline primary keys get a generated name (INTEG_*), so look it up.
        return sprintf(
            'execute block as declare c varchar(63); begin '
            .'select trim(rdb$constraint_name) from rdb$relation_constraints '
            .'where rdb$relation_name = %s and rdb$constraint_type = \'PRIMARY KEY\' into :c; '
            .'execute statement %s || c || \'"\'; end',
            $this->quoteString($this->getPrefixedTable($blueprint)),
            $this->quoteString('ALTER TABLE '.$this->wrapTable($blueprint).' DROP CONSTRAINT "'),
        );
    }

    /**
     * Compile a drop unique key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('ALTER TABLE %s DROP CONSTRAINT %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->index)
        );
    }

    /**
     * Compile a drop index command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        return 'DROP INDEX '.$this->wrap($command->index);
    }

    /**
     * Compile a drop column command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->prefixArray('DROP', $this->wrapArray($command->columns));

        return 'ALTER TABLE '.$this->wrapTable($blueprint).' '.implode(', ', $columns);
    }

    /**
     * Compile a change column command.
     *
     * Only the attributes that differ from the existing column are altered, as Firebird
     * rejects some no-op changes (e.g. dropping a default that doesn't exist, or
     * re-declaring the type of a key column).
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return list<string>
     *
     * @throws \LogicException
     */
    public function compileChange(Blueprint $blueprint, Fluent $command)
    {
        $column = $command->column;

        $current = (new Collection($this->connection->getSchemaBuilder()->getColumns($blueprint->getTable())))
            ->firstWhere('name', $column->name);

        if (! $current) {
            throw new \LogicException("Column [{$column->name}] does not exist.");
        }

        if ($column->type === 'enum') {
            throw new \LogicException('This database driver does not support changing enum columns.');
        }

        if ($column->collation && strcasecmp($column->collation, (string) $current['collation']) !== 0) {
            throw new \LogicException('This database driver does not support changing column collations.');
        }

        $alter = 'ALTER COLUMN '.$this->wrap($column).' ';
        $changes = [];

        $type = $this->getType($column).$this->modifyCharset($blueprint, $column);

        $normalize = fn ($type) => strtolower(str_replace(' ', '', $type));

        if ($column->charset || $normalize($type) !== $normalize($current['type'])) {
            $changes[] = $alter.'TYPE '.$type;
        }

        if ($default = $this->modifyDefault($blueprint, $column)) {
            $changes[] = $alter.'SET'.$default;
        } elseif (! is_null($current['default']) && ! $current['auto_increment']) {
            $changes[] = $alter.'DROP DEFAULT';
        }

        if ((bool) $column->nullable !== $current['nullable']) {
            $changes[] = $alter.($column->nullable ? 'DROP' : 'SET').' NOT NULL';
        }

        return $changes ? ['ALTER TABLE '.$this->wrapTable($blueprint).' '.implode(', ', $changes)] : [];
    }

    /**
     * Compile a rename column command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('ALTER TABLE %s ALTER COLUMN %s TO %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->from),
            $this->wrap($command->to)
        );
    }

    /**
     * Compile a rename table command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return never
     *
     * @throws \LogicException
     */
    public function compileRename(Blueprint $blueprint, Fluent $command)
    {
        throw new \LogicException('This database driver does not support renaming tables.');
    }

    /**
     * Compile a rename index command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return never
     *
     * @throws \LogicException
     */
    public function compileRenameIndex(Blueprint $blueprint, Fluent $command)
    {
        throw new \LogicException('This database driver does not support renaming indexes.');
    }

    /**
     * Get the table name with the connection's table prefix, as stored in the system tables.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @return string
     */
    protected function getPrefixedTable(Blueprint $blueprint)
    {
        return $this->connection->getTablePrefix().$blueprint->getTable();
    }

    /**
     * Add the column modifiers to the definition.
     *
     * @param  string  $sql
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     *
     * @throws \LogicException
     */
    protected function addModifiers($sql, Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->storedAs)) {
            throw new \LogicException('This database driver does not support stored generated columns.');
        }

        // Computed columns are always virtual and take no other modifiers.
        if (! is_null($column->virtualAs)) {
            return $sql.$this->modifyCharset($blueprint, $column)
                .' GENERATED ALWAYS AS ('.$this->getValue($column->virtualAs).')';
        }

        return parent::addModifiers($sql, $blueprint, $column);
    }

    /**
     * Get the SQL for a character set column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyCharset(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->charset)) {
            return ' CHARACTER SET '.$column->charset;
        }
    }

    /**
     * Get the SQL for a collation column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyCollate(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->collation)) {
            return ' COLLATE '.$column->collation;
        }
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (! $this->isIdentity($column)) {
            return null;
        }

        $sql = ' GENERATED BY DEFAULT AS IDENTITY';

        if ($start = $column->get('startingValue', $column->get('from'))) {
            $sql .= ' (START WITH '.(int) $start.')';
        }

        return $this->hasCommand($blueprint, 'primary') ? $sql : $sql.' PRIMARY KEY';
    }

    /**
     * Determine if the column is an identity (auto-increment) column.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return bool
     */
    protected function isIdentity(Fluent $column)
    {
        return $column->autoIncrement && in_array($column->type, $this->serials);
    }

    /**
     * Get the SQL for a nullable column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        return $column->nullable ? '' : ' NOT NULL';
    }

    /**
     * Get the SQL for a default column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        // Identity columns cannot have a default value.
        if ($this->isIdentity($column)) {
            return null;
        }

        // Firebird only casts the strings 'true' and 'false' to booleans.
        if ($column->type === 'boolean' && is_scalar($column->default)) {
            return filter_var($column->default, FILTER_VALIDATE_BOOLEAN) ? ' DEFAULT TRUE' : ' DEFAULT FALSE';
        }

        if (! is_null($column->default)) {
            return ' DEFAULT '.$this->getDefaultValue($column->default);
        }

        // CURRENT_TIMESTAMP is time zone aware, LOCALTIMESTAMP is not.
        if ($column->useCurrent) {
            return in_array($column->type, ['dateTimeTz', 'timestampTz'])
                ? ' DEFAULT CURRENT_TIMESTAMP'
                : ' DEFAULT LOCALTIMESTAMP';
        }
    }

    /**
     * Get the SQL for a check constraint column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyCheck(Blueprint $blueprint, Fluent $column)
    {
        if ($column->type === 'enum') {
            return sprintf(' CHECK (%s IN (%s))', $this->wrap($column), $this->quoteString($column->allowed));
        }
    }

    /**
     * Quote the given string literal.
     *
     * @param  string|array  $value
     * @return string
     */
    public function quoteString($value)
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, __FUNCTION__], $value));
        }

        return "'".str_replace("'", "''", enum_value($value))."'";
    }

    /**
     * Create the column definition for a char type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeChar(Fluent $column)
    {
        return "CHAR({$column->length})";
    }

    /**
     * Create the column definition for a string type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeString(Fluent $column)
    {
        return "VARCHAR({$column->length})";
    }

    /**
     * Create the column definition for a tiny text type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeTinyText(Fluent $column)
    {
        return 'VARCHAR(255)';
    }

    /**
     * Create the column definition for a text type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeText(Fluent $column)
    {
        return 'BLOB SUB_TYPE TEXT';
    }

    /**
     * Create the column definition for a medium text type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeMediumText(Fluent $column)
    {
        return 'BLOB SUB_TYPE TEXT';
    }

    /**
     * Create the column definition for a long text type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeLongText(Fluent $column)
    {
        return 'BLOB SUB_TYPE TEXT';
    }

    /**
     * Create the column definition for an integer type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeInteger(Fluent $column)
    {
        return 'INTEGER';
    }

    /**
     * Create the column definition for a big integer type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeBigInteger(Fluent $column)
    {
        return 'BIGINT';
    }

    /**
     * Create the column definition for a medium integer type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeMediumInteger(Fluent $column)
    {
        return 'INTEGER';
    }

    /**
     * Create the column definition for a tiny integer type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeTinyInteger(Fluent $column)
    {
        return 'SMALLINT';
    }

    /**
     * Create the column definition for a small integer type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeSmallInteger(Fluent $column)
    {
        return 'SMALLINT';
    }

    /**
     * Create the column definition for a float type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeFloat(Fluent $column)
    {
        // A precision of 1-24 is single precision, 25-53 is double precision.
        return $column->precision ? "FLOAT({$column->precision})" : 'FLOAT';
    }

    /**
     * Create the column definition for a double type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeDouble(Fluent $column)
    {
        return 'DOUBLE PRECISION';
    }

    /**
     * Create the column definition for a decimal type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeDecimal(Fluent $column)
    {
        return "DECIMAL({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a boolean type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeBoolean(Fluent $column)
    {
        return 'BOOLEAN';
    }

    /**
     * Create the column definition for an enumeration type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeEnum(Fluent $column)
    {
        return 'VARCHAR(255)';
    }

    /**
     * Create the column definition for a json type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeJson(Fluent $column)
    {
        return 'VARCHAR(8191)';
    }

    /**
     * Create the column definition for a jsonb type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeJsonb(Fluent $column)
    {
        return 'VARCHAR(8191) CHARACTER SET OCTETS';
    }

    /**
     * Create the column definition for a date type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeDate(Fluent $column)
    {
        return 'DATE';
    }

    /**
     * Create the column definition for a date-time type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeDateTime(Fluent $column)
    {
        return 'TIMESTAMP';
    }

    /**
     * Create the column definition for a date-time (with time zone) type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeDateTimeTz(Fluent $column)
    {
        return 'TIMESTAMP WITH TIME ZONE';
    }

    /**
     * Create the column definition for a year type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeYear(Fluent $column)
    {
        return 'SMALLINT';
    }

    /**
     * Create the column definition for a time type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeTime(Fluent $column)
    {
        return 'TIME';
    }

    /**
     * Create the column definition for a time (with time zone) type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeTimeTz(Fluent $column)
    {
        return 'TIME WITH TIME ZONE';
    }

    /**
     * Create the column definition for a timestamp type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeTimestamp(Fluent $column)
    {
        return 'TIMESTAMP';
    }

    /**
     * Create the column definition for a timestamp (with time zone) type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeTimestampTz(Fluent $column)
    {
        return 'TIMESTAMP WITH TIME ZONE';
    }

    /**
     * Create the column definition for a binary type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeBinary(Fluent $column)
    {
        if ($column->length) {
            return ($column->fixed ? 'BINARY' : 'VARBINARY')."({$column->length})";
        }

        return 'BLOB SUB_TYPE BINARY';
    }

    /**
     * Create the column definition for a uuid type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeUuid(Fluent $column)
    {
        return 'CHAR(36)';
    }

    /**
     * Create the column definition for an IP address type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeIpAddress(Fluent $column)
    {
        return 'VARCHAR(45)';
    }

    /**
     * Create the column definition for a MAC address type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeMacAddress(Fluent $column)
    {
        return 'VARCHAR(17)';
    }
}
