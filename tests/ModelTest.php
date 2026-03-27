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
}
