<?php

namespace HarryGulliford\Firebird\Tests;

use HarryGulliford\Firebird\Tests\Support\MigrateDatabase;
use HarryGulliford\Firebird\Tests\Support\Models\User;
use PHPUnit\Framework\Attributes\Test;

class ModelTest extends TestCase
{
    use MigrateDatabase;

    #[Test]
    public function it_can_create_a_record()
    {
        $user = User::create($fields = [
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'city' => 'Sydney',
            'state' => 'New South Wales',
            'post_code' => '2000',
            'country' => 'Australia',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->assertInstanceOf(User::class, $user);

        $this->assertDatabaseHas('users', $fields);

        $foundUser = User::find($user->id);

        $this->assertTrue($user->is($foundUser));

        $this->assertInstanceOf(User::class, $foundUser);

        // Check all fields have been persisted the model.
        foreach ($fields as $key => $value) {
            $this->assertEquals($value, $foundUser->{$key});
        }
    }

    #[Test]
    public function it_can_update_a_record()
    {
        $user = User::create([
            'name' => 'Before', 'email' => 'before@test.com',
            'city' => 'A', 'country' => 'B',
        ]);

        $user->update(['name' => 'After']);
        $user->refresh();

        $this->assertEquals('After', $user->name);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'After']);
    }

    #[Test]
    public function it_can_delete_a_record()
    {
        $user = User::create([
            'name' => 'ToDelete', 'email' => 'del@test.com',
            'city' => 'A', 'country' => 'B',
        ]);

        $id = $user->id;
        $user->delete();

        $this->assertNull(User::find($id));
        $this->assertDatabaseMissing('users', ['id' => $id]);
    }

    #[Test]
    public function it_can_find_a_record()
    {
        $user = User::create([
            'name' => 'Findable', 'email' => 'find@test.com',
            'city' => 'A', 'country' => 'B',
        ]);

        $found = User::find($user->id);
        $this->assertNotNull($found);
        $this->assertEquals('Findable', $found->name);
    }

    #[Test]
    public function it_can_query_with_where()
    {
        User::create(['name' => 'AAA', 'email' => 'a@test.com', 'city' => 'Rome', 'country' => 'Italy']);
        User::create(['name' => 'BBB', 'email' => 'b@test.com', 'city' => 'Milan', 'country' => 'Italy']);
        User::create(['name' => 'CCC', 'email' => 'c@test.com', 'city' => 'London', 'country' => 'UK']);

        $italians = User::where('country', 'Italy')->get();
        $this->assertCount(2, $italians);
    }

    #[Test]
    public function it_can_use_first_and_value()
    {
        User::create(['name' => 'First', 'email' => 'first@test.com', 'city' => 'X', 'country' => 'Y']);

        $user = User::first();
        $this->assertInstanceOf(User::class, $user);

        $name = User::where('email', 'first@test.com')->value('name');
        $this->assertEquals('First', $name);
    }
}
