<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use App\Support\Measurement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SizeGuide extends Model
{
    use TracksAdmin;

    public const UNITS = ['in', 'cm'];

    public const DEFAULT_UNIT = 'in';

    public const UNIT_LABELS = ['in' => 'Inch (in)', 'cm' => 'Centimeter (cm)'];

    protected $table = 'size_guides';

    protected $guarded = [];

    public function sizeAttributes(): HasMany
    {
        return $this->hasMany(SizeGuideAttribute::class)->orderBy('sort_order');
    }

    public function sizeValues(): HasMany
    {
        return $this->hasMany(SizeGuideValue::class)->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The unit the SAVED numbers are in. Reliable stored data first: when every attribute carries the same recognised
     * unit that is it (older Admin code wrote the attribute unit but left the column at its 'in' default); otherwise
     * the guide column; otherwise 'cm' (the original attribute default). Never blindly 'in' for existing data.
     */
    public function storedUnit(): string
    {
        $units = $this->sizeAttributes->map(fn ($a) => Measurement::unit($a->unit))->unique()->values();
        if ($units->count() === 1 && $units[0]) {
            return $units[0];
        }

        return Measurement::unit($this->measurement_unit) ?? ($this->exists ? 'cm' : self::DEFAULT_UNIT);
    }

    /** Stored unit of one attribute's values (mixed legacy guides differ per attribute); falls back to the guide's. */
    public function attributeUnit(SizeGuideAttribute $a): string
    {
        return Measurement::unit($a->unit) ?? $this->storedUnit();
    }
}
