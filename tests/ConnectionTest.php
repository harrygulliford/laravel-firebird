<?php

namespace HarryGulliford\Firebird\Tests;

use HarryGulliford\Firebird\Query\Builder as FirebirdQueryBuilder;
use HarryGulliford\Firebird\Query\Grammars\FirebirdGrammar as FirebirdQueryGrammar;
use HarryGulliford\Firebird\Query\Processors\FirebirdProcessor as FirebirdQueryProcessor;
use HarryGulliford\Firebird\Schema\Builder as FirebirdSchemaBuilder;
use HarryGulliford\Firebird\Schema\Grammars\FirebirdGrammar as FirebirdSchemaGrammar;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class ConnectionTest extends TestCase
{
    #[Test]
    public function it_gets_server_version()
    {
        $connection = DB::connection();

        $expectedVersion = $connection->selectOne('select rdb$get_context(\'SYSTEM\', \'ENGINE_VERSION\') as "version" from rdb$database');
        $expectedVersion = $expectedVersion->version;

        $version = $connection->getServerVersion();

        $this->assertEquals($expectedVersion, $version);
        $this->assertTrue(version_compare($expectedVersion, $version, '=='));

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }

    #[Test]
    public function it_gets_default_query_grammar()
    {
        $connection = DB::connection();

        $grammar = $connection->getQueryGrammar();

        $this->assertInstanceOf(FirebirdQueryGrammar::class, $grammar);
    }

    #[Test]
    public function it_gets_default_post_processor()
    {
        $connection = DB::connection();

        $processor = $connection->getPostProcessor();

        $this->assertInstanceOf(FirebirdQueryProcessor::class, $processor);
    }

    #[Test]
    public function it_gets_schema_builder()
    {
        $connection = DB::connection();

        $schemaBuilder = $connection->getSchemaBuilder();

        $this->assertInstanceOf(FirebirdSchemaBuilder::class, $schemaBuilder);
    }

    #[Test]
    public function it_gets_schema_grammar()
    {
        $connection = DB::connection();

        $connection->useDefaultSchemaGrammar();

        $grammar = $connection->getSchemaGrammar();

        $this->assertInstanceOf(FirebirdSchemaGrammar::class, $grammar);
    }

    #[Test]
    public function it_gets_query_builder()
    {
        $connection = DB::connection();

        $queryBuilder = $connection->query();

        $this->assertInstanceOf(FirebirdQueryBuilder::class, $queryBuilder);
    }
}
