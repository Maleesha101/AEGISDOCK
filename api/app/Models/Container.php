<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Container extends Model
{
    protected $fillable = ['container_number', 'terminal_id', 'owner', 'cargo_type', 'status'];

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContainerEvent::class);
    }
}