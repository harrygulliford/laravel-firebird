<?php

namespace HarryGulliford\Firebird\Tests;

use HarryGulliford\Firebird\Tests\Support\Factories\UserFactory;
use HarryGulliford\Firebird\Tests\Support\MigrateDatabase;
use HarryGulliford\Firebird\Tests\Support\Models\User;
use PHPUnit\Framework\Attributes\Test;

class ModelTest extends TestCase
{
    use MigrateDatabase;

    #[Test]
    public function it_can_create_a_record()
    {
        User::create($fields = [
            'id' => $id = UserFactory::$id++, // Firebird < 3 does not support auto-incrementing columns.
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'city' => 'Sydney',
            'state' => 'New South Wales',
            'post_code' => '2000',
            'country' => 'Australia',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $user = User::find($id);

        $this->assertInstanceOf(User::class, $user);

        // Check all fields have been persisted the model.
        foreach ($fields as $key => $value) {
            $this->assertEquals($value, $user->{$key});
        }
    }
}
