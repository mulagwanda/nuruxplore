<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NuruxploreSection extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'section_number',
        'content',
        'ai_metadata',
        'status',
        'word_count',
        'order',
        'parent_id',
    ];

    protected $casts = [
        'ai_metadata' => 'array',
        'word_count' => 'integer',
        'order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(NuruxploreProject::class, 'project_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(NuruxploreSection::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(NuruxploreSection::class, 'parent_id')->orderBy('order');
    }

    protected static function booted()
    {
        static::saved(function ($section) {
            if ($section->content) {
                $section->word_count = str_word_count(strip_tags($section->content));
                $section->saveQuietly();
                $section->project->updateWordCount();
            }
        });
    }
}