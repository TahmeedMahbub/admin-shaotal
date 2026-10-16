<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;

class SizeGuideAttribute extends Model
{
    use TracksAdmin;

    protected $table = 'size_guide_attributes';

    protected $guarded = [];
}
