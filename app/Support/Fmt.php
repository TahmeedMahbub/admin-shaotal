<?php

namespace App\Support;

class Fmt
{
    public static function money($value): string
    {
        return 'A$'.number_format((float) $value, 2);
    }

    /** Badge colour class for any status string used across modules. */
    public static function badge(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'active', 'paid', 'delivered', 'approved', 'resolved', 'completed', 'accepted', 'subscribed', 'in_stock', 'converted', 'visible', 'clicked' => 'bg-label-success',
            'pending', 'new', 'draft', 'quoted', 'in_progress', 'reviewing', 'processing', 'authorized', 'preorder', 'coming_soon', 'sent', 'not_sent' => 'bg-label-warning',
            'confirmed', 'shipped' => 'bg-label-info',
            'cancelled', 'rejected', 'failed', 'blocked', 'spam', 'returned', 'out_of_stock', 'expired', 'abandoned' => 'bg-label-danger',
            default => 'bg-label-secondary',
        };
    }
}
