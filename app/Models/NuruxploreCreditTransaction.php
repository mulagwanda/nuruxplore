<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NuruxploreCreditTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'reason',
        'project_id',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(NuruxploreProject::class, 'project_id');
    }
}