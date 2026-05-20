<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NuruxploreVersion extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'version_number',
        'snapshot',
        'changes_description',
        'change_type',
        'ai_interaction_log',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'ai_interaction_log' => 'array',
        'version_number' => 'integer',
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