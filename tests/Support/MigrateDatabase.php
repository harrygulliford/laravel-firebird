<?php

namespace HarryGulliford\Firebird\Tests\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait MigrateDatabase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! MigrationState::$migrated) {
            $this->dropTables();
            $this->createTables();

            $this->dropProcedures();
            $this->createProcedures();

            MigrationState::$migrated = true;
        }
    }

    protected function tearDown(): void
    {
        DB::table('orders')->delete();
        DB::table('users')->delete();

        parent::tearDown();
    }

    public function createTables(): void
    {
        DB::select('CREATE TABLE "users" ("id" INTEGER GENERATED ALWAYS AS IDENTITY NOT NULL PRIMARY KEY, "name" VARCHAR(255) NOT NULL, "email" VARCHAR(255) NOT NULL, "city" VARCHAR(255), "state" VARCHAR(255), "post_code" VARCHAR(255), "country" VARCHAR(255), "created_at" TIMESTAMP, "updated_at" TIMESTAMP, "deleted_at" TIMESTAMP)');

        DB::select('CREATE TABLE "orders" ("id" INTEGER GENERATED ALWAYS AS IDENTITY NOT NULL PRIMARY KEY, "user_id" INTEGER NOT NULL, "name" VARCHAR(255) NOT NULL, "price" INTEGER NOT NULL, "quantity" INTEGER NOT NULL, "created_at" TIMESTAMP, "updated_at" TIMESTAMP, "deleted_at" TIMESTAMP)');
        DB::select('ALTER TABLE "orders" ADD CONSTRAINT "orders_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users" ("id")');
    }

    public function dropTables(): void
    {
        $tables = [
            'orders',
            'users',
            // Can be left behind if the test suite exits unexpectedly:
            'contacts',
            'foo',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function createProcedures()
    {
        $procedure = 'math_multiply';
        $resultColumn = 'result';

        $sql = sprintf(
            'create procedure %s (a integer, b integer) returns (%s integer) as begin %s = a * b; suspend; end',
            DB::getQueryGrammar()->wrap($procedure),
            DB::getQueryGrammar()->wrap($resultColumn),
            DB::getQueryGrammar()->wrap($resultColumn),
        );

        DB::select($sql);
    }

    public function dropProcedures()
    {
        $procedures = [
            'math_multiply',
        ];

        foreach ($procedures as $procedure) {
            try {
                DB::select('drop procedure '.DB::getQueryGrammar()->wrap($procedure));
            } catch (QueryException $e) {
                // Suppress the "not found" exception.
                if (! Str::contains($e->getMessage(), 'not found')) {
                    throw $e;
                }
            }
        }
    }
}
