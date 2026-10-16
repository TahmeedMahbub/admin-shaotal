<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    use TracksAdmin;

    protected $table = 'contact_messages';

    protected $guarded = [];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assigned_admin_id');
    }
}
