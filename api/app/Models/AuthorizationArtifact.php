<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationArtifact extends Model
{
    protected $fillable = [
        'user_id',
        'terminal_id',
        'container_id',
        'ciphertext',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }
}