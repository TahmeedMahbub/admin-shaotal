<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;

class SourcingRequestImage extends Model
{
    use TracksAdmin;

    protected $table = 'sourcing_request_images';

    protected $guarded = [];

    public $timestamps = false;
}
