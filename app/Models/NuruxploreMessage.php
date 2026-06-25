<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NuruxploreMessage extends Model
{
    protected $fillable = [
        'project_id', 'user_id', 'role', 'content', 'metadata', 'action_type', 'credits_used',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(NuruxploreProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
