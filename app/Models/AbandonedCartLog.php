<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;

class AbandonedCartLog extends Model
{
    use TracksAdmin;

    protected $table = 'abandoned_cart_logs';

    protected $guarded = [];

    public $timestamps = false;
}
