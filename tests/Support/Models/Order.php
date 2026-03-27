<?php

namespace HarryGulliford\Firebird\Tests\Support\Models;

use HarryGulliford\Firebird\Tests\Support\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $guarded = [];

    public static $factory = OrderFactory::class;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
