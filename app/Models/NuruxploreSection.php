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

    public function summary(): ?string
    {
        return $this->ai_metadata['summary'] ?? null;
    }

    public function setMetadataValue(string $key, mixed $value): void
    {
        $metadata = $this->ai_metadata ?? [];
        $metadata[$key] = $value;
        $this->forceFill(['ai_metadata' => $metadata])->save();
    }

    protected static function booted(): void
    {
        static::saving(function ($section) {
            if (array_key_exists('content', $section->getAttributes())) {
                $section->word_count = str_word_count(strip_tags((string) $section->content));
            }
        });

        static::saved(function ($section) {
            if ($section->relationLoaded('project')) {
                $section->project->updateWordCount();
                return;
            }

            $project = $section->project;
            if ($project) {
                $project->updateWordCount();
            }
        });
    }
}
