<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantOrder extends Model
{
    protected $primaryKey = 'order_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['order_id', 'lines', 'total_cents'];

    protected function casts(): array
    {
        return ['lines' => 'array', 'total_cents' => 'integer'];
    }
}