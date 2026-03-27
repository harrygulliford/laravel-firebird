<?php

namespace HarryGulliford\Firebird\Tests\Support;

use HarryGulliford\Firebird\Tests\Support\Factories\OrderFactory;
use HarryGulliford\Firebird\Tests\Support\Factories\UserFactory;
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

        // Reset the static ids on the factory, as Firebird <= 3 does not
        // support auto-incrementing ids.
        // TODO: Like to figure out a way of using auto-incrementing ids for
        // newer versions of Firebird, but not ready to drop v2.5 support yet.
        UserFactory::$id = 1;
        OrderFactory::$id = 1;

        parent::tearDown();
    }

    public function createTables(): void
    {
        DB::select('CREATE TABLE "users" ("id" INTEGER NOT NULL, "name" VARCHAR(255) NOT NULL, "email" VARCHAR(255) NOT NULL, "city" VARCHAR(255), "state" VARCHAR(255), "post_code" VARCHAR(255), "country" VARCHAR(255), "created_at" TIMESTAMP, "updated_at" TIMESTAMP, "deleted_at" TIMESTAMP)');
        DB::select('ALTER TABLE "users" ADD PRIMARY KEY ("id")');

        DB::select('CREATE TABLE "orders" ("id" INTEGER NOT NULL, "user_id" INTEGER NOT NULL, "name" VARCHAR(255) NOT NULL, "price" INTEGER NOT NULL, "quantity" INTEGER NOT NULL, "created_at" TIMESTAMP, "updated_at" TIMESTAMP, "deleted_at" TIMESTAMP)');
        DB::select('ALTER TABLE "orders" ADD CONSTRAINT orders_user_id_foreign FOREIGN KEY ("user_id") REFERENCES "users" ("id")');
        DB::select('ALTER TABLE "orders" ADD PRIMARY KEY ("id")');
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
        DB::select(
            'CREATE PROCEDURE MULTIPLY (a INTEGER, b INTEGER)
                RETURNS (result INTEGER)
            AS BEGIN
                result = a * b;
                SUSPEND;
            END'
        );
    }

    public function dropProcedures()
    {
        try {
            DB::select('DROP PROCEDURE MULTIPLY');
        } catch (QueryException $e) {
            // Suppress the "procedure not found" exception, as we want to
            // replicate dropIfExists() functionality without using the Schema
            // class.
            if (! Str::contains($e->getMessage(), 'not found')) {
                throw $e;
            }
        }
    }
}
