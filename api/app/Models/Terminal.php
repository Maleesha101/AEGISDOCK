<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Terminal extends Model
{
    protected $fillable = ['code', 'name', 'location', 'status'];

    public function containers(): HasMany
    {
        return $this->hasMany(Container::class);
    }
}