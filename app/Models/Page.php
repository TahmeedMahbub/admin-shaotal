<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use TracksAdmin;

    protected $guarded = [];
}
