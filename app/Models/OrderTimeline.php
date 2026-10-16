<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTimeline extends Model
{
    protected $table = 'order_timeline';

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    public $timestamps = false;

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'changed_by_admin_id');
    }
}
