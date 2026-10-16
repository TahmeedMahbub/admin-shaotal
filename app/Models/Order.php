<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use TracksAdmin;

    protected $table = 'orders';

    protected $guarded = [];

    protected $casts = ['placed_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(OrderTimeline::class)->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
