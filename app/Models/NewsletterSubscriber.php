<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use TracksAdmin;

    protected $table = 'newsletter_subscribers';

    protected $guarded = [];

    protected $casts = ['subscribed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
}
