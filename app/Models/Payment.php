<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use TracksAdmin;

    protected $table = 'payments';

    protected $guarded = [];

    protected $casts = ['response_data' => 'array'];
}
