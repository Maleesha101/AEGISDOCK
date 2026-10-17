<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlagSubmission extends Model
{
    protected $fillable = ['user_id', 'flag_code', 'submitted_at'];

    protected $casts = ['submitted_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
