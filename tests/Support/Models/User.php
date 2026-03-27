<?php

namespace HarryGulliford\Firebird\Tests\Support\Models;

use HarryGulliford\Firebird\Tests\Support\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $guarded = [];

    public static $factory = UserFactory::class;

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
