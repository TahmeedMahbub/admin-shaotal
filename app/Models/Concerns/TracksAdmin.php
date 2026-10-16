<?php

namespace App\Models\Concerns;

/** Fills created_by / updated_by with the signed-in admin id on admin-made writes. */
trait TracksAdmin
{
    public static function bootTracksAdmin(): void
    {
        static::creating(function ($model) {
            $id = auth('admin')->id();
            if ($id) {
                $model->created_by ??= $id;
                $model->updated_by ??= $id;
            }
        });
        static::updating(function ($model) {
            if ($id = auth('admin')->id()) {
                $model->updated_by = $id;
            }
        });
    }
}
