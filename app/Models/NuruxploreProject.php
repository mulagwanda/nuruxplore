<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NuruxploreProject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'citation_style',
        'description',
        'research_question',
        'structure',
        'word_count',
        'status',
        'last_edited_at',
    ];

    protected $casts = [
        'structure' => 'array',
        'last_edited_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(NuruxploreSection::class, 'project_id')->orderBy('order');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(NuruxploreSource::class, 'project_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(NuruxploreMessage::class, 'project_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(NuruxploreVersion::class, 'project_id');
    }

    public function updateWordCount(): void
    {
        $this->word_count = $this->sections()->sum('word_count');
        $this->save();
    }
}