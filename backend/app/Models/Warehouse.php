<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $guarded = [];

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function operators(): HasMany
    {
        return $this->hasMany(Operator::class);
    }
}
