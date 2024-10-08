<?php

namespace HarryGulliford\Firebird\Tests;

use HarryGulliford\Firebird\Tests\Support\MigrateDatabase;
use HarryGulliford\Firebird\Tests\Support\Models\Order;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class PaginationTest extends TestCase
{
    use MigrateDatabase;

    #[Test]
    public function it_can_paginate_results()
    {
        Order::factory()->count(10)->create(['price' => 50]);

        $paginator = DB::table('orders')->paginate(3, ['id', 'price'], 'orders');
        $this->assertCount(3, $paginator);
        $this->assertEquals(10, $paginator->total());
        $this->assertTrue($paginator->hasMorePages());

        $paginator = DB::table('orders')->paginate(3, ['id', 'price'], 'orders', 2);
        $this->assertCount(3, $paginator);
        $this->assertEquals(10, $paginator->total());
        $this->assertTrue($paginator->hasMorePages());
    }

    #[Test]
    public function it_can_simple_paginate_results()
    {
        Order::factory()->count(10)->create(['price' => 50]);

        $paginator = DB::table('orders')->simplePaginate(3);
        $this->assertCount(3, $paginator);
        $this->assertTrue($paginator->hasMorePages());

        $paginator = DB::table('orders')->simplePaginate(3, ['id', 'price'], 'orders', 2);
        $this->assertCount(3, $paginator);
        $this->assertTrue($paginator->hasMorePages());
    }

    #[Test]
    public function it_can_cursor_paginate_results()
    {
        Order::factory()->count(10)->create(['price' => 50]);

        $paginator = DB::table('orders')->orderBy('id')->cursorPaginate(3, ['id', 'price'], 'orders');
        $this->assertCount(3, $paginator);

        $paginator = DB::table('orders')->orderBy('id')->cursorPaginate(3, ['id', 'price'], 'orders', $paginator->nextCursor());
        $this->assertCount(3, $paginator);
    }
}
