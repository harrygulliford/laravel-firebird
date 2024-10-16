<?php

namespace HarryGulliford\Firebird\Tests;

use HarryGulliford\Firebird\Tests\Support\MigrateDatabase;
use HarryGulliford\Firebird\Tests\Support\Models\Order;
use HarryGulliford\Firebird\Tests\Support\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class QueryTest extends TestCase
{
    use MigrateDatabase;

    #[Test]
    public function it_has_the_correct_connection()
    {
        $this->assertEquals('firebird', DB::getDefaultConnection());
    }

    #[Test]
    public function it_can_get()
    {
        Order::factory()->count(3)->create();

        $users = DB::table('users')->get();

        $this->assertCount(3, $users);
        $this->assertInstanceOf(Collection::class, $users);
        $this->assertIsObject($users->first());
        $this->assertIsArray($users->toArray());

        $orders = DB::table('orders')->get();

        $this->assertCount(3, $orders);
        $this->assertInstanceOf(Collection::class, $orders);
        $this->assertIsObject($orders->first());
        $this->assertIsArray($orders->toArray());
    }

    #[Test]
    public function it_can_select()
    {
        User::factory()->create([
            'name' => 'Anna',
            'city' => 'Sydney',
            'country' => 'Australia',
        ]);

        $result = DB::table('users')
            ->select(['name', 'city', 'country'])
            ->first();

        $this->assertCount(3, (array) $result);

        $this->assertObjectHasProperty('name', $result);
        $this->assertObjectHasProperty('city', $result);
        $this->assertObjectHasProperty('country', $result);

        $this->assertEquals('Anna', $result->name);
        $this->assertEquals('Sydney', $result->city);
        $this->assertEquals('Australia', $result->country);
    }

    #[Test]
    public function it_can_select_with_aliases()
    {
        User::factory()->create([
            'name' => 'Anna',
            'city' => 'Sydney',
            'country' => 'Australia',
        ]);

        $result = DB::table('users')
            ->select([
                'name as USER_NAME',
                'city as user_city',
                'country as User_Country',
            ])
            ->first();

        $this->assertCount(3, (array) $result);

        $this->assertObjectHasProperty('USER_NAME', $result);
        $this->assertObjectHasProperty('user_city', $result);
        $this->assertObjectHasProperty('User_Country', $result);

        $this->assertEquals('Anna', $result->USER_NAME);
        $this->assertEquals('Sydney', $result->user_city);
        $this->assertEquals('Australia', $result->User_Country);
    }

    #[Test]
    public function it_can_select_distinct()
    {
        Order::factory()->count(1)->create(['price' => 10]);
        Order::factory()->count(10)->create(['price' => 50]);
        Order::factory()->count(5)->create(['price' => 100]);

        $results = DB::table('orders')
            ->select('price')
            ->distinct()
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_can_filter_where_with_results()
    {
        User::factory()->count(5)->create(['name' => 'Frank']);
        User::factory()->count(2)->create(['name' => 'Inigo']);
        User::factory()->count(7)->create(['name' => 'Ashley']);

        $results = DB::table('users')
            ->where('name', 'Frank')
            ->get();

        $this->assertCount(5, $results);
        $this->assertCount(1, $results->pluck('name')->unique());
        $this->assertEquals('Frank', $results->random()->name);
    }

    #[Test]
    public function it_can_filter_where_without_results()
    {
        User::factory()->count(2)->create();

        $results = DB::table('users')
            ->where('name', Str::random(24))
            ->get();

        $this->assertCount(0, $results);
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertEquals([], $results->toArray());
        $this->assertNull($results->first());
    }

    #[Test]
    public function it_can_filter_where_gt()
    {
        Order::factory()
            ->count(8)
            ->state(new Sequence(
                ['price' => 5],
                ['price' => 25],
                ['price' => 50],
                ['price' => 99],
                ['price' => 100],
                ['price' => 101],
                ['price' => 150],
                ['price' => 200],
            ))
            ->create();

        $results = DB::table('orders')
            ->where('price', '>', 100)
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_can_filter_where_gte()
    {
        Order::factory()
            ->count(8)
            ->state(new Sequence(
                ['price' => 5],
                ['price' => 25],
                ['price' => 50],
                ['price' => 99],
                ['price' => 100],
                ['price' => 101],
                ['price' => 150],
                ['price' => 200],
            ))
            ->create();

        $results = DB::table('orders')
            ->where('price', '>=', 100)
            ->get();

        $this->assertCount(4, $results);
    }

    #[Test]
    public function it_can_filter_where_lt()
    {
        Order::factory()
            ->count(8)
            ->state(new Sequence(
                ['price' => 5],
                ['price' => 25],
                ['price' => 50],
                ['price' => 99],
                ['price' => 100],
                ['price' => 101],
                ['price' => 150],
                ['price' => 200],
            ))
            ->create();

        $results = DB::table('orders')
            ->where('price', '<', 100)
            ->get();

        $this->assertCount(4, $results);
    }

    #[Test]
    public function it_can_filter_where_lte()
    {
        Order::factory()
            ->count(8)
            ->state(new Sequence(
                ['price' => 5],
                ['price' => 25],
                ['price' => 50],
                ['price' => 99],
                ['price' => 100],
                ['price' => 101],
                ['price' => 150],
                ['price' => 200],
            ))
            ->create();

        $results = DB::table('orders')
            ->where('price', '<=', 100)
            ->get();

        $this->assertCount(5, $results);
    }

    #[Test]
    public function it_can_filter_where_not_equal()
    {
        Order::factory()
            ->count(8)
            ->state(new Sequence(
                ['price' => 5],
                ['price' => 25],
                ['price' => 50],
                ['price' => 99],
                ['price' => 100],
                ['price' => 101],
                ['price' => 150],
                ['price' => 200],
            ))
            ->create();

        $results = DB::table('orders')
            ->where('price', '!=', 100)
            ->get();

        $this->assertCount(7, $results);

        $results = DB::table('orders')
            ->where('price', '<>', 100)
            ->get();

        $this->assertCount(7, $results);
    }

    #[Test]
    public function it_can_filter_where_like()
    {
        // "Like" is case-sensitive. For case-insensitive, use "containing".

        Order::factory()->create(['name' => 'Pants Small']);
        Order::factory()->create(['name' => 'Pants Large']);
        Order::factory()->create(['name' => 'Shirt Small']);
        Order::factory()->create(['name' => 'Shirt Medium']);
        Order::factory()->create(['name' => 'Shirt Large']);

        $results = DB::table('orders')
            ->where('name', 'like', 'Shirt%')
            ->get();

        $this->assertCount(3, $results);

        $results = DB::table('orders')
            ->where('name', 'like', '%Small')
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_can_filter_where_not_like()
    {
        Order::factory()->create(['name' => 'Pants Small']);
        Order::factory()->create(['name' => 'Pants Large']);
        Order::factory()->create(['name' => 'Shirt Small']);
        Order::factory()->create(['name' => 'Shirt Medium']);
        Order::factory()->create(['name' => 'Shirt Large']);

        $results = DB::table('orders')
            ->where('name', 'not like', 'Shirt%')
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_can_filter_where_array()
    {
        Order::factory()->create(['name' => 'Pants Small', 'price' => 60]);
        Order::factory()->create(['name' => 'Pants Large', 'price' => 80]);
        Order::factory()->create(['name' => 'Shirt Small', 'price' => 50]);
        Order::factory()->create(['name' => 'Shirt Medium', 'price' => 60]);
        Order::factory()->create(['name' => 'Shirt Large', 'price' => 70]);

        $results = DB::table('orders')
            ->where([
                ['price', '>=', 60],
                ['name', 'like', '%Large%'],
                ['name', 'not like', '%Pants%'],
            ])
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_can_filter_or_where()
    {
        Order::factory()
            ->count(8)
            ->state(new Sequence(
                ['price' => 5],
                ['price' => 25],
                ['price' => 50],
                ['price' => 99],
                ['price' => 100],
                ['price' => 100],
                ['price' => 150],
                ['price' => 200],
            ))
            ->create();

        $results = DB::table('orders')
            ->where('price', 100)
            ->orWhere('price', 5)
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_can_filter_grouped_or_where()
    {
        Order::factory()->count(2)->create(['price' => 100]);
        Order::factory()->create(['price' => 25, 'quantity' => 1]);
        Order::factory()->create(['price' => 30, 'quantity' => 3]);

        $results = DB::table('orders')
            ->where('price', '>=', 100)
            ->orWhere(function ($query) {
                $query->where('price', '>', 10)
                    ->where('quantity', '>', 2);
            })
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_can_filter_where_in()
    {
        Order::factory()->count(1)->create(['price' => 75]);
        Order::factory()->count(3)->create(['price' => 100]);
        Order::factory()->count(5)->create(['price' => 125]);

        $results = DB::table('orders')
            ->whereIn('price', [100, 125])
            ->get();

        $this->assertCount(8, $results);
    }

    #[Test]
    public function it_can_filter_where_in_exceeds_firebird_2_limit()
    {
        Order::factory()
            ->count(1505)
            ->for(User::factory())
            ->create(['price' => 100]);

        $results = DB::table('orders')
            ->whereIn('price', [100])
            ->count();

        $this->assertEquals(1505, $results);
    }

    #[Test]
    public function it_can_filter_where_not_in()
    {
        Order::factory()->count(1)->create(['price' => 75]);
        Order::factory()->count(3)->create(['price' => 100]);
        Order::factory()->count(5)->create(['price' => 125]);

        $results = DB::table('orders')
            ->whereNotIn('price', [100, 125])
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_can_filter_where_between()
    {
        Order::factory()->create(['price' => 10]);
        Order::factory()->create(['price' => 20]);
        Order::factory()->create(['price' => 30]);
        Order::factory()->create(['price' => 40]);
        Order::factory()->create(['price' => 50]);

        $results = DB::table('orders')
            ->whereBetween('price', [30, 60])
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_can_filter_where_not_between()
    {
        Order::factory()->create(['price' => 10]);
        Order::factory()->create(['price' => 20]);
        Order::factory()->create(['price' => 30]);
        Order::factory()->create(['price' => 40]);
        Order::factory()->create(['price' => 50]);

        $results = DB::table('orders')
            ->whereNotBetween('price', [30, 60])
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_can_filter_where_null()
    {
        Order::factory()->count(10)->create();

        $results = DB::table('orders')
            ->whereNull('deleted_at')
            ->get();

        $this->assertCount(10, $results);
    }

    #[Test]
    public function it_can_filter_where_not_null()
    {
        Order::factory()->count(10)->create();

        $results = DB::table('orders')
            ->whereNotNull('created_at')
            ->get();

        $this->assertCount(10, $results);
    }

    #[Test]
    public function it_can_filter_where_date()
    {
        $now = now()->toImmutable();

        Order::factory()
            ->forEachSequence(
                ['created_at' => $now->subYearNoOverflow()],
                ['created_at' => $now->subYear()],
                ['created_at' => $now->subMonthNoOverflow()],
                ['created_at' => $now->subMonth()],
                ['created_at' => $now->subWeek()],
                ['created_at' => $now->subDay()],
                ['created_at' => $now],
                ['created_at' => $now->addDay()],
            )
            ->create();

        $results = DB::table('orders')
            ->whereDate('created_at', now())
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_can_filter_where_time()
    {
        $now = now()->toImmutable();

        // When a DateTimeInterface is passed to whereTime(), the time is
        // accurate to the second.

        Order::factory()
            ->forEachSequence(
                ['created_at' => $now->subHour()],
                ['created_at' => $now->subMinute()],
                ['created_at' => $now->subSecond()],
                ['created_at' => $now->subMillisecond()],
                ['created_at' => $now],
                ['created_at' => $now->addSecond()],
            )
            ->create();

        $results = DB::table('orders')
            ->whereTime('created_at', $now)
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_can_filter_where_day()
    {
        Order::factory()->count(3)->create(['created_at' => now()]);
        Order::factory()->count(5)->create(['created_at' => now()->subDay()]);

        $results = DB::table('orders')
            ->whereDay('created_at', now())
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_can_filter_where_month()
    {
        Order::factory()->count(3)->create(['created_at' => now()]);
        Order::factory()->count(5)->create(['created_at' => now()->subMonthNoOverflow()]);

        $results = DB::table('orders')
            ->whereMonth('created_at', now())
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_can_filter_where_year()
    {
        Order::factory()->count(3)->create(['created_at' => now()]);
        Order::factory()->count(5)->create(['created_at' => now()->subYearNoOverflow()]);

        $results = DB::table('orders')
            ->whereYear('created_at', now())
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_can_filter_where_containing()
    {
        // "Containing" is a case-insensitive alternative to "like". Also, the
        // % wildcard operators are not required.

        Order::factory()->create(['name' => 'Pants Small']);
        Order::factory()->create(['name' => 'Pants Large']);
        Order::factory()->create(['name' => 'Shirt Small']);
        Order::factory()->create(['name' => 'Shirt Medium']);
        Order::factory()->create(['name' => 'Shirt Large']);

        $results = DB::table('orders')
            ->where('name', 'containing', 'shirt')
            ->get();

        $this->assertCount(3, $results);

        $results = DB::table('orders')
            ->where('name', 'containing', 'small')
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_can_filter_where_not_containing()
    {
        // "Containing" is a case-insensitive alternative to "like". Also, the
        // % wildcard operators are not required.

        Order::factory()->create(['name' => 'Pants Small']);
        Order::factory()->create(['name' => 'Pants Large']);
        Order::factory()->create(['name' => 'Shirt Small']);
        Order::factory()->create(['name' => 'Shirt Medium']);
        Order::factory()->create(['name' => 'Shirt Large']);

        $results = DB::table('orders')
            ->where('name', 'not containing', 'shirt')
            ->get();

        $this->assertCount(2, $results);

        $results = DB::table('orders')
            ->where('name', 'not containing', 'small')
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_can_filter_where_starting_with()
    {
        Order::factory()->create(['name' => 'Pants Small']);
        Order::factory()->create(['name' => 'Pants Large']);
        Order::factory()->create(['name' => 'Shirt Small']);
        Order::factory()->create(['name' => 'Shirt Medium']);
        Order::factory()->create(['name' => 'Shirt Large']);

        $results = DB::table('orders')
            ->where('name', 'starting with', 'Shirt')
            ->get();

        $this->assertCount(3, $results);

        $results = DB::table('orders')
            ->where('name', 'starting with', 'Pants')
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_can_filter_where_not_starting_with()
    {
        Order::factory()->create(['name' => 'Pants Small']);
        Order::factory()->create(['name' => 'Pants Large']);
        Order::factory()->create(['name' => 'Shirt Small']);
        Order::factory()->create(['name' => 'Shirt Medium']);
        Order::factory()->create(['name' => 'Shirt Large']);

        $results = DB::table('orders')
            ->where('name', 'not starting with', 'Shirt')
            ->get();

        $this->assertCount(2, $results);

        $results = DB::table('orders')
            ->where('name', 'not starting with', 'Pants')
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_can_filter_where_similar_to()
    {
        Order::factory()->create(['name' => 'Pants Small']);
        Order::factory()->create(['name' => 'Pants Large']);
        Order::factory()->create(['name' => 'Shirt Small']);
        Order::factory()->create(['name' => 'Shirt Medium']);
        Order::factory()->create(['name' => 'Shirt Large']);

        $results = DB::table('orders')
            ->where('name', 'similar to', 'Pants (Medium|Large)')
            ->get();

        $this->assertCount(1, $results);

        $results = DB::table('orders')
            ->where('name', 'similar to', 'Shirt (Medium|Large)')
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_can_filter_where_not_similar_to()
    {
        Order::factory()->create(['name' => 'Pants Small']);
        Order::factory()->create(['name' => 'Pants Large']);
        Order::factory()->create(['name' => 'Shirt Small']);
        Order::factory()->create(['name' => 'Shirt Medium']);
        Order::factory()->create(['name' => 'Shirt Large']);

        $results = DB::table('orders')
            ->where('name', 'not similar to', 'Pants (Medium|Large)')
            ->get();

        $this->assertCount(4, $results);

        $results = DB::table('orders')
            ->where('name', 'not similar to', 'Shirt (Medium|Large)')
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_can_filter_where_is_distinct_from()
    {
        User::factory()->create(['state' => null]);
        User::factory()->create(['state' => 'NY']);
        User::factory()->create(['state' => 'AK']);

        $results = DB::table('users')
            ->where('state', 'is distinct from', 'NY')
            ->get();

        $this->assertCount(2, $results);

        $results = DB::table('users')
            ->where('state', 'is distinct from', null)
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_can_filter_where_is_not_distinct_from()
    {
        User::factory()->create(['state' => null]);
        User::factory()->create(['state' => 'NY']);
        User::factory()->create(['state' => 'AK']);

        $results = DB::table('users')
            ->where('state', 'is not distinct from', 'NY')
            ->get();

        $this->assertCount(1, $results);

        $results = DB::table('users')
            ->where('state', 'is not distinct from', null)
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_can_filter_where_exists()
    {
        Order::factory()->count(2)->create(['price' => 120]);
        Order::factory()->count(3)->create(['price' => 80]);

        $results = DB::table('users')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('orders')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->where('price', '>', 100);
            })
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_can_filter_subquery_where()
    {
        Order::factory()->count(2)->create(['price' => 100]);
        Order::factory()->count(3)->create(['price' => 80]);
        Order::factory()->count(6)->create(['price' => 120]);

        $results = DB::table('users')
            ->where(function ($query) {
                $query->select('price')
                    ->from('orders')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->limit(1);
            }, 100)
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_can_order_by_asc()
    {
        Order::factory()->create(['price' => 100]);
        Order::factory()->create(['price' => 200]);
        Order::factory()->create(['price' => 300]);

        $results = DB::table('orders')->orderBy('price')->get();

        $this->assertEquals(100, $results->first()->price);
        $this->assertEquals(300, $results->last()->price);
    }

    #[Test]
    public function it_can_order_by_desc()
    {
        Order::factory()->create(['price' => 100]);
        Order::factory()->create(['price' => 200]);
        Order::factory()->create(['price' => 300]);

        $results = DB::table('orders')->orderByDesc('price')->get();

        $this->assertEquals(300, $results->first()->price);
        $this->assertEquals(100, $results->last()->price);
    }

    #[Test]
    public function it_can_order_latest()
    {
        Order::factory()->create(['price' => 100, 'created_at' => now()]);
        Order::factory()->create(['price' => 200, 'created_at' => now()->subMonthsNoOverflow(1)]);
        Order::factory()->create(['price' => 300, 'created_at' => now()->subMonthsNoOverflow(2)]);

        $results = DB::table('orders')->latest()->get();

        $this->assertEquals(100, $results->first()->price);
        $this->assertEquals(300, $results->last()->price);
    }

    #[Test]
    public function it_can_order_oldest()
    {
        Order::factory()->create(['price' => 100, 'created_at' => now()]);
        Order::factory()->create(['price' => 200, 'created_at' => now()->subMonthsNoOverflow(1)]);
        Order::factory()->create(['price' => 300, 'created_at' => now()->subMonthsNoOverflow(2)]);

        $results = DB::table('orders')->oldest()->get();

        $this->assertEquals(300, $results->first()->price);
        $this->assertEquals(100, $results->last()->price);
    }

    #[Test]
    public function it_can_return_random_order()
    {
        User::factory()->count(25)->create();

        $resultsA = DB::table('users')->inRandomOrder()->get();
        $resultsB = DB::table('users')->inRandomOrder()->get();
        $resultsC = DB::table('users')->inRandomOrder()->get();

        $this->assertNotEquals($resultsA, $resultsB);
        $this->assertNotEquals($resultsA, $resultsC);
        $this->assertNotEquals($resultsB, $resultsC);
    }

    #[Test]
    public function it_can_remove_existing_orderings()
    {
        Order::factory()->count(10)->create();

        $query = DB::table('orders')->orderByDesc('id');

        $results = $query->get();

        $lowestId = $results->min('id');
        $highestId = $results->max('id');

        $this->assertEquals($highestId, $results->first()->id);
        $this->assertEquals($lowestId, $results->last()->id);

        $results = $query->reorder()->get();

        $this->assertEquals($lowestId, $results->first()->id);
        $this->assertEquals($highestId, $results->last()->id);
    }

    #[Test]
    public function it_can_pluck()
    {
        $prices = array_map(fn () => fake()->unique()->numberBetween(1, 1000), range(1, 10));

        $this->assertCount(10, $prices);

        Order::factory()
            ->forEachSequence(...array_map(fn ($price) => ['price' => $price], $prices))
            ->create();

        $results = DB::table('orders')->pluck('price');

        $this->assertCount(10, $results);
        foreach ($prices as $expectedPrice) {
            $this->assertContains($expectedPrice, $results);
        }
    }

    #[Test]
    public function it_can_count()
    {
        Order::factory()->count(10)->create();

        $count = DB::table('orders')->count();

        $this->assertEquals(10, $count);
    }

    #[Test]
    public function it_can_aggregate_max()
    {
        Order::factory()
            ->count(5)
            ->state(new Sequence(
                ['price' => 68],
                ['price' => 92],
                ['price' => 12],
                ['price' => 37],
                ['price' => 54],
            ))
            ->create();

        $price = DB::table('orders')->max('price');

        $this->assertEquals(92, $price);
    }

    #[Test]
    public function it_can_aggregate_min()
    {
        Order::factory()
            ->count(5)
            ->state(new Sequence(
                ['price' => 68],
                ['price' => 92],
                ['price' => 12],
                ['price' => 37],
                ['price' => 54],
            ))
            ->create();

        $price = DB::table('orders')->min('price');

        $this->assertEquals(12, $price);
    }

    #[Test]
    public function it_can_aggregate_average()
    {
        Order::factory()
            ->count(5)
            ->state(new Sequence(
                ['price' => 68],
                ['price' => 92],
                ['price' => 12],
                ['price' => 37],
                ['price' => 54],
            ))
            ->create();

        $price = DB::table('orders')->avg('price');

        $this->assertEquals((int) 52.6, $price);
    }

    #[Test]
    public function it_can_aggregate_sum()
    {
        Order::factory()
            ->count(5)
            ->state(new Sequence(
                ['price' => 68],
                ['price' => 92],
                ['price' => 12],
                ['price' => 37],
                ['price' => 54],
            ))
            ->create();

        $price = DB::table('orders')->sum('price');

        $this->assertEquals(263, $price);
    }

    #[Test]
    public function it_can_check_exists()
    {
        User::factory()->create(['name' => $name = fake()->name()]);

        $this->assertTrue(DB::table('users')->where('name', $name)->exists());
        $this->assertFalse(DB::table('users')->where('name', uniqid('__invalid__'))->exists());
        $this->assertFalse(DB::table('users')->where('id', null)->exists());
    }

    #[Test]
    public function it_can_execute_raw_expressions()
    {
        Order::factory()
            ->count(6)
            ->state(new Sequence(
                ['price' => 50],
                ['price' => 50],
                ['price' => 70],
                ['price' => 90],
                ['price' => 90],
                ['price' => 90],
            ))
            ->create();

        $results = DB::table('orders')
            ->select(DB::raw('count(*) as "price_count", "price"'))
            ->groupBy('price')
            ->get();

        $this->assertCount(3, $results);
        $this->assertEquals(2, $results->where('price', 50)->first()->price_count);
        $this->assertEquals(1, $results->where('price', 70)->first()->price_count);
        $this->assertEquals(3, $results->where('price', 90)->first()->price_count);
    }

    #[Test]
    public function it_can_execute_raw_select_containing_arithmetic()
    {
        if (version_compare($this->getConnection()->getServerVersion(), '4.0', '>=')) {
            // Ref: https://github.com/FirebirdSQL/php-firebird/issues/26
            $this->markTestSkipped('Skipped due to an issue with DECIMAL or NUMERIC types in the PHP Firebird PDO extension for database engine version 4.0+');
        }

        Order::factory()
            ->count(3)
            ->state(new Sequence(
                ['price' => 50],
                ['price' => 70],
                ['price' => 90],
            ))
            ->create();

        $results = DB::table('orders')
            ->selectRaw('"price", "price" * 1.1 as "price_with_tax"')
            ->get();

        foreach ($results as $result) {
            $this->assertEquals(round($result->price * 1.1), $result->price_with_tax);
        }
    }

    #[Test]
    public function it_can_execute_raw_select_containing_sum()
    {
        Order::factory()
            ->count(3)
            ->state(new Sequence(
                ['price' => 50],
                ['price' => 70],
                ['price' => 90],
            ))
            ->create();

        $result = DB::table('orders')
            ->selectRaw('SUM("price") as "price"')
            ->get()
            ->first();

        $this->assertEquals(210, $result->price);
    }

    #[Test]
    public function it_can_execute_raw_where()
    {
        User::factory()->count(3)->create();
        User::factory()->create(['city' => null]);

        $results = DB::table('users')
            ->whereRaw('"city" is not null')
            ->get();

        $this->assertCount(3, $results);
    }

    #[Test]
    public function it_can_execute_raw_order_by()
    {
        Order::factory()
            ->count(3)
            ->state(new Sequence(
                ['price' => 50, 'quantity' => 10],
                ['price' => 70, 'quantity' => 5],
                ['price' => 90, 'quantity' => 1],
            ))
            ->create();

        $results = DB::table('orders')
            ->orderByRaw('"price" * "quantity" desc')
            ->get();

        $max = $results->first()->price * $results->first()->quantity;
        $min = $results->last()->price * $results->last()->quantity;

        $this->assertEquals(500, $max);
        $this->assertEquals(90, $min);
    }

    #[Test]
    public function it_can_add_inner_join()
    {
        Order::factory()->count(10)->create();

        $results = DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->get();

        $this->assertCount(10, $results);
        $this->assertObjectHasProperty('name', $results->first());
        $this->assertObjectHasProperty('email', $results->first());
        $this->assertObjectHasProperty('state', $results->first());
        $this->assertObjectHasProperty('price', $results->first());
        $this->assertObjectHasProperty('quantity', $results->first());
    }

    #[Test]
    public function it_can_add_inner_join_where()
    {
        Order::factory()->count(2)->create(['price' => 100]);
        Order::factory()->count(3)->create(['price' => 50]);

        $results = DB::table('orders')
            ->join('users', function ($join) {
                $join->on('users.id', '=', 'orders.user_id')
                    ->where('orders.price', 100);
            })
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals(100, $results->first()->price);

        $this->assertObjectHasProperty('name', $results->first());
        $this->assertObjectHasProperty('email', $results->first());
        $this->assertObjectHasProperty('state', $results->first());
        $this->assertObjectHasProperty('price', $results->first());
        $this->assertObjectHasProperty('quantity', $results->first());
    }

    #[Test]
    public function it_can_add_inner_join_lateral()
    {
        if (version_compare($this->getConnection()->getServerVersion(), '4.0', '<')) {
            $this->markTestSkipped('Skipped due to lack of support for lateral joins');
        }

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Order::factory()->create(['user_id' => $user1->id, 'price' => 100, 'created_at' => now()->subDays(1)]);
        Order::factory()->create(['user_id' => $user1->id, 'price' => 150, 'created_at' => now()]);
        Order::factory()->create(['user_id' => $user2->id, 'price' => 75, 'created_at' => now()->subDays(2)]);
        Order::factory()->create(['user_id' => $user2->id, 'price' => 50, 'created_at' => now()]);

        $results = DB::table('users')
            ->joinLateral(function (Builder $builder) {
                $builder->from('orders')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->orderBy('orders.created_at', 'desc')
                    ->offset(0)
                    ->limit(1);
            }, 'latest_order')
            ->select('users.*', 'latest_order.price as latest_order_price')
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals(150, $results->firstWhere('id', $user1->id)->latest_order_price);
        $this->assertEquals(50, $results->firstWhere('id', $user2->id)->latest_order_price);

        $this->assertObjectHasProperty('name', $results->first());
        $this->assertObjectHasProperty('email', $results->first());
        $this->assertObjectHasProperty('latest_order_price', $results->first());
    }

    #[Test]
    public function it_can_add_left_join()
    {
        Order::factory()->count(2)->create(['price' => 100]);
        Order::factory()->count(3)->create(['price' => 50]);

        $results = DB::table('orders')
            ->leftJoin('users', function ($join) {
                $join->on('users.id', '=', 'orders.user_id')
                    ->where('orders.price', 100);
            })
            ->get();

        $this->assertCount(5, $results);
        $this->assertCount(0, $results->whereNull('price'));
        $this->assertCount(3, $results->whereNull('email'));
    }

    #[Test]
    public function it_can_add_left_join_lateral()
    {
        if (version_compare($this->getConnection()->getServerVersion(), '4.0', '<')) {
            $this->markTestSkipped('Skipped due to lack of support for lateral joins');
        }

        $user1 = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user2 = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        Order::factory()->create(['user_id' => $user1->id, 'price' => 100, 'created_at' => now()->subDays(1)]);
        Order::factory()->create(['user_id' => $user1->id, 'price' => 150, 'created_at' => now()]);
        Order::factory()->create(['user_id' => $user2->id, 'price' => 50, 'created_at' => now()->subDays(2)]);
        Order::factory()->create(['user_id' => $user2->id, 'price' => 75, 'created_at' => now()]);

        $results = DB::table('users')
            ->leftJoinLateral(function (Builder $builder) {
                $builder->from('orders')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->orderBy('orders.created_at', 'desc')
                    ->limit(1);
            }, 'latest_order')
            ->select('users.*', 'latest_order.price as latest_order_price')
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals(150, $results->firstWhere('name', 'John Doe')->latest_order_price);
        $this->assertEquals(75, $results->firstWhere('name', 'Jane Doe')->latest_order_price);

        $this->assertObjectHasProperty('name', $results->first());
        $this->assertObjectHasProperty('email', $results->first());
        $this->assertObjectHasProperty('latest_order_price', $results->first());
    }

    #[Test]
    public function it_can_add_right_join()
    {
        Order::factory()->count(2)->create(['price' => 100]);
        Order::factory()->count(3)->create(['price' => 50]);

        $results = DB::table('orders')
            ->rightJoin('users', function ($join) {
                $join->on('users.id', '=', 'orders.user_id')
                    ->where('orders.price', 100);
            })
            ->get();

        $this->assertCount(5, $results);
        $this->assertCount(3, $results->whereNull('price'));
        $this->assertCount(0, $results->whereNull('email'));
    }

    #[Test]
    public function it_can_add_subquery_join()
    {
        Order::factory()->create();

        $latestOrder = DB::table('orders')
            ->select('user_id', DB::raw('MAX("created_at") as "last_order_created_at"'))
            ->groupBy('user_id');

        $user = DB::table('users')
            ->joinSub($latestOrder, 'latest_order', function ($join) {
                $join->on('users.id', '=', 'latest_order.user_id');
            })->first();

        $this->assertNotNull($user->last_order_created_at);
    }

    #[Test]
    public function it_can_union_queries()
    {
        Order::factory()
            ->count(5)
            ->state(new Sequence(
                ['price' => 110],
                ['price' => 100],
                ['price' => 100],
                ['price' => 80],
                ['price' => 16],
            ))
            ->create();

        $first = DB::table('orders')
            ->where('price', 100);

        $orders = DB::table('orders')
            ->where('price', 16)
            ->union($first)
            ->get();

        $this->assertCount(3, $orders);
    }

    #[Test]
    public function it_can_group_having()
    {
        User::factory()->count(5)->create(['country' => 'Australia']);
        User::factory()->count(3)->create(['country' => 'New Zealand']);
        User::factory()->count(2)->create(['country' => 'England']);

        $results = DB::table('users')
            ->selectRaw('count("id") as "count", "country"')
            ->groupBy('country')
            ->having('country', '!=', 'England')
            ->get();

        $this->assertCount(2, $results);
        $results = $results->mapWithKeys(fn ($result) => [$result->country => $result->count]);
        $this->assertEquals(5, $results['Australia']);
        $this->assertEquals(3, $results['New Zealand']);
    }

    #[Test]
    public function it_can_group_having_raw()
    {
        User::factory()->count(5)->create(['country' => 'Australia']);
        User::factory()->count(3)->create(['country' => 'New Zealand']);
        User::factory()->count(2)->create(['country' => 'England']);

        $results = DB::table('users')
            ->selectRaw('count("id") as "count", "country"')
            ->groupBy('country')
            ->havingRaw('count("id") > 2')
            ->get();

        $this->assertCount(2, $results);
        $results = $results->mapWithKeys(fn ($result) => [$result->country => $result->count]);
        $this->assertEquals(5, $results['Australia']);
        $this->assertEquals(3, $results['New Zealand']);
    }

    #[Test]
    public function it_can_offset_results()
    {
        $users = User::factory()
            ->count($count = 10)
            ->create();

        $startingId = $users->first()->id;

        // Check the starting id is the lowest id.
        $this->assertEquals($startingId, $users->min('id'));

        $results = DB::table('users')
            ->offset($offset = 3)
            ->get();

        $this->assertCount($count - $offset, $results);

        $this->assertEquals($startingId + $offset, $results->first()->id);
        $this->assertEquals($startingId + $count - 1, $results->last()->id);

        // Check the order of the results.
        $this->assertTrue($results->first()->id < $results->last()->id);
    }

    #[Test]
    public function it_can_limit_and_offset_results()
    {
        User::factory()->count(10)->create();

        $startingId = DB::table('users')
            ->value('id');

        $results = DB::table('users')
            ->limit($limit = 3)
            ->offset($offset = 3)
            ->get();

        $this->assertCount(3, $results);

        $this->assertEquals($startingId + $offset, $results->first()->id);
        $this->assertEquals($startingId + $offset + ($limit - 1), $results->last()->id);

        // Check the order of the results.
        $this->assertTrue($results->first()->id < $results->last()->id);
    }

    #[Test]
    public function it_can_limit_results()
    {
        User::factory()->count(10)->create();

        $startingId = DB::table('users')
            ->value('id');

        $results = DB::table('users')
            ->limit($limit = 3)
            ->get();

        $this->assertCount(3, $results);

        $this->assertEquals($startingId, $results->first()->id);
        $this->assertEquals($startingId + ($limit - 1), $results->last()->id);

        // Check the order of the results.
        $this->assertTrue($results->first()->id < $results->last()->id);
    }

    // /** @test */
    // public function it_can_insert_returning_id()
    // {
    //     $id = DB::table('users')
    //         ->insertGetId([
    //             'name' => 'Anna',
    //             'city' => 'Sydney',
    //             'country' => 'Australia',
    //         ]);

    //     dd($id);
    // }

    #[Test]
    public function it_can_execute_stored_procedures()
    {
        $firstNumber = random_int(1, 10);
        $secondNumber = random_int(1, 10);

        $result = DB::query()
            ->fromProcedure('MULTIPLY', [
                $firstNumber, $secondNumber,
            ])
            ->first()
            ->RESULT;

        $this->assertEquals($firstNumber * $secondNumber, $result);
    }
}
