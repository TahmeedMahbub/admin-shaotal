<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;

class SiteContent extends Model
{
    use TracksAdmin;

    protected $table = 'site_contents';

    protected $guarded = [];
}
